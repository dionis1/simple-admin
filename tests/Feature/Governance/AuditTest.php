<?php

use App\Actions\Memberships\UpdateMembership;
use App\Enums\RoleName;
use App\Governance\Audit;
use App\Governance\AuditHistory;
use App\Livewire\Governance\History;
use App\Models\AuditEvent;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('records redacted changes and real actor for membership and permission assignment changes', function () {
    [$owner, $org] = tenantWorkspace();
    $target = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($owner, $org);
    app(UpdateMembership::class)($owner, $target->id, tenantRole($org, RoleName::Manager)->id, 'active');
    $event = AuditEvent::where('organization_id', $org->id)->where('event', 'role_assignment.updated')->latest('id')->firstOrFail();
    expect($event->actor_id)->toBe($owner->id)->and(Str::isUuid($event->correlation_id))->toBeTrue()
        ->and($event->changes['role_id']['before'])->toBe(tenantRole($org, RoleName::Viewer)->id)
        ->and($event->changes['role_id']['after'])->toBe(tenantRole($org, RoleName::Manager)->id);
    $entry = app(Audit::class)->record('test.redaction', $org->id, 'User', $owner->id, ['password' => 'plaintext', 'token' => 'secret', 'reason' => 'private reason', 'email' => $owner->email, 'status' => ['before' => 'active', 'after' => ['password' => 'nested-secret']]]);
    expect(json_encode($entry->changes))->not->toContain('plaintext', 'secret', 'private reason', 'nested-secret', $owner->email);
    expect($entry->changes['password'])->toBe('[REDACTED]');
});

it('rolls back audit and resource changes together and refuses audit updates or deletes', function () {
    [$owner, $org] = tenantWorkspace();
    tenantActor($owner, $org);
    $before = AuditEvent::count();
    try {
        DB::transaction(function () use ($org) {
            $org->forceFill(['name' => 'This should roll back'])->save();
            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }
    expect($org->fresh()->name)->toBe('Test workspace')->and(AuditEvent::count())->toBe($before);
    $id = AuditEvent::where('organization_id', $org->id)->firstOrFail()->id;
    expect(fn () => DB::transaction(fn () => DB::table('audit_events')->where('id', $id)->update(['event' => 'forged'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('audit_events')->where('id', $id)->delete()))->toThrow(QueryException::class);
});

it('isolates audit search export and dashboard from foreign tenant data', function () {
    [$owner, $org] = tenantWorkspace();
    [$other, $foreign] = tenantWorkspace();
    tenantActor($other, $foreign);
    app(Audit::class)->record('foreign.unique_event', $foreign->id, 'Organization', $foreign->id);
    tenantActor($owner, $org);
    app(Audit::class)->record('local.unique_event', $org->id, 'Organization', $org->id);
    Livewire::test(History::class)->set('search', 'unique_event')->assertSee('local.unique_event')->assertDontSee('foreign.unique_event');
    $csv = $this->get(route('audit.export', ['search' => 'unique_event']))->assertOk()->streamedContent();
    expect($csv)->toContain('local.unique_event', 'subject_user_id')->not->toContain('foreign.unique_event', $other->email);
    $this->get(route('dashboard'))->assertOk()->assertDontSee('foreign.unique_event');
    $viewer = tenantMember($owner, $org, RoleName::Viewer)->user()->firstOrFail();
    tenantActor($viewer, $org);
    $this->get(route('audit.index'))->assertForbidden();
    $this->get(route('audit.export'))->assertForbidden();
    $this->get(route('approvals.index'))->assertForbidden();
});

it('escapes spreadsheet formulas and never trusts inbound correlation IDs', function () {
    $history = app(AuditHistory::class);
    foreach (['=1+1', '+cmd', '-1', '@SUM(A1)', "\t=2", '  =2'] as $value) {
        expect($history->cell($value))->toBe("'".$value);
    }
    [$owner, $org] = tenantWorkspace();
    tenantActor($owner, $org);
    $response = $this->withHeader('X-Correlation-ID', 'attacker')->get(route('audit.export'));
    $response->assertOk();
    expect($response->headers->get('X-Correlation-ID'))->not->toBe('attacker')
        ->and(Str::isUuid($response->headers->get('X-Correlation-ID')))->toBeTrue();
});

it('enforces every governance permission for every role', function (string $role, string $permission, bool $allowed) {
    [$owner, $org] = tenantWorkspace();
    $actor = $role === 'owner' ? $owner : tenantMember($owner, $org, RoleName::from($role))->user()->firstOrFail();
    tenantActor($actor, $org);
    expect(app(TenantContext::class)->allows($actor, $permission))->toBe($allowed);
})->with(function () {
    foreach (RoleName::cases() as $role) {
        foreach (['audit.view', 'audit.export', 'approvals.request', 'approvals.review', 'support.request', 'operations.view'] as $permission) {
            yield $role->value.' '.$permission => [$role->value, $permission, in_array($role, [RoleName::Owner, RoleName::Admin], true) || ($role === RoleName::Support && $permission === 'support.request')];
        }
    }
});

it('fails closed and rolls back a model save when the audit insert fails', function () {
    [$owner, $org] = tenantWorkspace();
    tenantActor($owner, $org);
    $name = $owner->name;
    AuditEvent::creating(function (AuditEvent $event): void {
        if ($event->event === 'user.updated') {
            throw new RuntimeException('Simulated audit storage failure');
        }
    });
    try {
        expect(fn () => $owner->forceFill(['name' => 'Must not persist'])->save())->toThrow(RuntimeException::class);
        expect($owner->fresh()->name)->toBe($name);
    } finally {
        AuditEvent::flushEventListeners();
    }
});
