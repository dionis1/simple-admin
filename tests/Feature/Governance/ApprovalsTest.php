<?php

use App\Actions\Governance\DecideApproval;
use App\Actions\Governance\RequestApproval;
use App\Actions\Security\IssueApiToken;
use App\Enums\RoleName;
use App\Livewire\Governance\Approvals;
use App\Models\AuditEvent;
use App\Models\PersonalAccessToken;
use App\Models\SupportGrant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('executes an independently approved organization token purge once and leaves other tenants intact', function () {
    [$owner, $org] = tenantWorkspace();
    $reviewer = tenantMember($owner, $org, RoleName::Admin)->user()->firstOrFail();
    [$other, $foreign] = tenantWorkspace();
    tenantActor($other, $foreign);
    app(IssueApiToken::class)($other, 'password', 'Foreign token', ['memberships.view'], 1);
    tenantActor($owner, $org);
    app(IssueApiToken::class)($owner, 'password', 'Local token', ['memberships.view'], 1);
    $request = app(RequestApproval::class)($owner, 'revoke_org_tokens', 'Rotate organization credentials after an incident.');
    expect(fn () => app(DecideApproval::class)($owner, $request->id, true, 'password'))->toThrow(AuthorizationException::class);
    tenantActor($reviewer, $org);
    app(DecideApproval::class)($reviewer, $request->id, true, 'password');
    expect($request->refresh()->state)->toBe('executed')
        ->and(PersonalAccessToken::where('organization_id', $org->id)->count())->toBe(0)
        ->and(PersonalAccessToken::where('organization_id', $foreign->id)->count())->toBe(1);
    expect(fn () => app(DecideApproval::class)($reviewer, $request->id, true, 'password'))->toThrow(HttpException::class);
    expect(AuditEvent::where('event', 'tokens.organization_revoked')->count())->toBe(1);
});

it('rejects requester target and reviewer privilege violations', function () {
    [$owner, $org] = tenantWorkspace();
    $support = tenantMember($owner, $org, RoleName::Support)->user()->firstOrFail();
    $viewer = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($support, $org);
    expect(fn () => app(RequestApproval::class)($support, 'revoke_org_tokens', 'Unauthorized purge request.'))->toThrow(AuthorizationException::class);
    $request = app(RequestApproval::class)($support, 'support_access', 'Investigate a reported read issue.', $viewer->id);
    tenantActor($viewer->user()->firstOrFail(), $org);
    expect(fn () => app(DecideApproval::class)($viewer->user()->firstOrFail(), $request->id, true, 'password'))->toThrow(AuthorizationException::class);
    tenantActor($owner, $org);
    $support->memberships()->where('organization_id', $org->id)->update(['status' => 'suspended']);
    expect(fn () => app(DecideApproval::class)($owner, $request->id, true, 'password'))->toThrow(AuthorizationException::class);
    expect($request->refresh()->state)->toBe('pending')->and(SupportGrant::count())->toBe(0);
});

it('does not execute expired rejected or cancelled requests', function (string $state) {
    [$owner, $org] = tenantWorkspace();
    $reviewer = tenantMember($owner, $org, RoleName::Admin)->user()->firstOrFail();
    tenantActor($owner, $org);
    $request = app(RequestApproval::class)($owner, 'revoke_org_tokens', 'Organization credentials require rotation.');
    if ($state === 'expired') {
        $request->forceFill(['expires_at' => now()->subSecond()])->save();
    } elseif ($state === 'cancelled') {
        Livewire::test(Approvals::class)->call('cancel', $request->id)->assertHasNoErrors();
    }
    tenantActor($reviewer, $org);
    if ($state === 'cancelled') {
        expect(fn () => app(DecideApproval::class)($reviewer, $request->id, true, 'password'))->toThrow(HttpException::class);
    } else {
        app(DecideApproval::class)($reviewer, $request->id, $state === 'expired', 'password');
    }
    expect($request->refresh()->state)->toBe($state)->and($request->getAttribute('executed_at'))->toBeNull();
})->with(['expired', 'rejected', 'cancelled']);

it('rolls back the decision and audit when execution fails and forbids cross-tenant review', function () {
    [$owner, $org] = tenantWorkspace();
    $admin = tenantMember($owner, $org, RoleName::Admin)->user()->firstOrFail();
    $viewer = tenantMember($owner, $org, RoleName::Viewer);
    tenantActor($owner, $org);
    $request = app(RequestApproval::class)($owner, 'support_access', 'Investigate a reported interface issue.', $viewer->id);
    [$foreign, $other] = tenantWorkspace();
    tenantActor($foreign, $other);
    expect(fn () => app(DecideApproval::class)($foreign, $request->id, true, 'password'))->toThrow(ModelNotFoundException::class);
    tenantActor($admin, $org);
    $viewer->forceFill(['status' => 'suspended'])->save();
    $before = AuditEvent::count();
    expect(fn () => app(DecideApproval::class)($admin, $request->id, true, 'password'))->toThrow(HttpException::class);
    expect($request->refresh()->state)->toBe('pending')->and(AuditEvent::count())->toBe($before)->and(SupportGrant::count())->toBe(0);
});

it('has a database boundary against self approval', function () {
    [$owner, $org] = tenantWorkspace();
    tenantActor($owner, $org);
    $request = app(RequestApproval::class)($owner, 'revoke_org_tokens', 'Organization credentials need rotation.');
    expect(fn () => DB::transaction(fn () => DB::table('approval_requests')->where('id', $request->id)->update(['reviewer_id' => $owner->id])))->toThrow(QueryException::class);
});

it('validates governance forms and prevents forged Livewire decisions', function () {
    [$owner, $org] = tenantWorkspace();
    tenantActor($owner, $org);
    Livewire::test(Approvals::class)->set('reason', 'short')->call('requestApproval')->assertHasErrors(['reason', 'targetId']);
    $request = app(RequestApproval::class)($owner, 'revoke_org_tokens', 'Rotate credentials after a security review.');
    Livewire::test(Approvals::class)->set('currentPassword', 'password')->call('decide', $request->id, true)->assertForbidden();
    expect($request->refresh()->state)->toBe('pending');
});
