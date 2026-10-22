<?php

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\SwitchOrganization;
use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Livewire\Organizations\Index;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('atomically creates a workspace with fixed roles permissions and one owner', function () {
    [$owner, $organization] = tenantWorkspace();
    tenantActor($owner, $organization);

    expect($organization->roles()->count())->toBe(5)
        ->and($organization->memberships()->count())->toBe(1)
        ->and(app(TenantContext::class)->resolve($owner)->assignedRole()->name)->toBe(RoleName::Owner);
    $this->assertDatabaseCount('permissions', 15);
    $this->assertDatabaseCount('role_assignments', 1);
});

it('rejects missing malformed foreign and stale tenant sessions', function (mixed $value) {
    [$owner, $organization] = tenantWorkspace();
    [$other, $foreign] = tenantWorkspace(name: 'Foreign workspace');
    $value = $value === 'foreign' ? $foreign->id : $value;
    $this->actingAs($owner)->withSession([TenantContext::SESSION_KEY => $value]);

    $this->get(route('dashboard'))->assertForbidden();
    expect(Gate::forUser($owner)->allows('tenant.permission', 'memberships.create'))->toBeFalse();
})->with([null, 'invalid', -1, 9999999, ['malformed'], 'foreign', '999999999999999999999999999999', 1.5]);

it('rejects suspended memberships organizations and missing role assignments', function (string $condition) {
    [$owner, $organization] = tenantWorkspace();
    tenantActor($owner, $organization);
    $membership = $organization->memberships()->firstOrFail();
    match ($condition) {
        'membership' => $membership->forceFill(['status' => AccessStatus::Suspended])->save(),
        'organization' => $organization->forceFill(['status' => AccessStatus::Suspended])->save(),
        'assignment' => $membership->roleAssignment()->delete(),
    };

    $this->get(route('dashboard'))->assertForbidden();
    Livewire::test(Index::class)->call('choose', $organization->id)->assertForbidden();
})->with(['membership', 'organization', 'assignment']);

it('lists only the signed in users organizations and rejects forged switching', function () {
    [$owner, $organization] = tenantWorkspace(name: 'Visible workspace');
    [, $foreign] = tenantWorkspace(name: 'Hidden workspace');
    tenantActor($owner, $organization);

    Livewire::test(Index::class)->assertSee('Visible workspace')->assertDontSee('Hidden workspace')
        ->call('choose', $foreign->id)->assertForbidden();
    expect(session(TenantContext::SESSION_KEY))->toBe($organization->id);
});

it('keeps permissions separate when one user switches between organizations', function () {
    [$firstOwner, $first] = tenantWorkspace();
    $user = User::factory()->create();
    tenantMember($firstOwner, $first, RoleName::Viewer, $user);
    [, $second] = tenantWorkspace($user, 'Second workspace');
    tenantActor($user, $first);

    expect(Gate::forUser($user)->allows('tenant.permission', 'memberships.create'))->toBeFalse();
    app(SwitchOrganization::class)($user, $second->id);
    expect(Gate::forUser($user)->allows('tenant.permission', 'memberships.create'))->toBeTrue();
    app(SwitchOrganization::class)($user, $first->id);
    expect(Gate::forUser($user)->allows('tenant.permission', 'memberships.create'))->toBeFalse();
});

it('does not accept a browser organization header or query in place of validated session context', function () {
    [$owner, $organization] = tenantWorkspace();
    $this->actingAs($owner)->withSession([TenantContext::SESSION_KEY => null])
        ->withHeader('X-Organization-ID', (string) $organization->id)
        ->get(route('dashboard').'?organization_id='.$organization->id)->assertForbidden();
});

it('rechecks revoked access instead of trusting an already loaded membership', function () {
    [$owner, $organization] = tenantWorkspace();
    tenantActor($owner, $organization);
    $context = app(TenantContext::class);
    $membership = $context->resolve($owner);
    expect($context->allows($owner, 'dashboard.view'))->toBeTrue();
    $membership->forceFill(['status' => AccessStatus::Suspended])->save();
    expect($context->allows($owner, 'dashboard.view'))->toBeFalse();
    $this->get(route('dashboard'))->assertForbidden();
});

it('requires verification to create or switch a workspace', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user)->get(route('organizations.index'))->assertRedirect(route('verification.notice'));
    expect(fn () => app(CreateOrganization::class)($user, 'Forbidden'))->toThrow(AuthorizationException::class);
    expect(fn () => app(SwitchOrganization::class)($user, 1))->toThrow(HttpException::class);
    $this->assertDatabaseCount('organizations', 0);
});

it('creates and opens organizations through Livewire and validates names', function () {
    $this->actingAs(User::factory()->create());
    Livewire::test(Index::class)->set('name', '')->call('create')->assertHasErrors('name');
    Livewire::test(Index::class)->set('name', 'New workspace')->call('create')->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('organizations', ['name' => 'New workspace']);
    expect(session(TenantContext::SESSION_KEY))->toBeInt();
});
