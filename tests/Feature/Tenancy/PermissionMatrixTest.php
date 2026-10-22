<?php

use App\Actions\Organizations\UpdateOrganization;
use App\Enums\RoleName;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

$matrix = [
    'owner' => ['dashboard.view', 'organizations.view', 'organizations.update', 'memberships.view', 'memberships.create', 'memberships.update', 'memberships.delete', 'roles.view', 'roles.assign', 'permissions.view'],
    'admin' => ['dashboard.view', 'organizations.view', 'organizations.update', 'memberships.view', 'memberships.create', 'memberships.update', 'memberships.delete', 'roles.view', 'roles.assign', 'permissions.view'],
    'manager' => ['dashboard.view', 'organizations.view', 'memberships.view', 'roles.view', 'permissions.view'],
    'support' => ['dashboard.view', 'organizations.view', 'memberships.view'],
    'viewer' => ['dashboard.view', 'organizations.view'],
];
$permissions = $matrix['owner'];
$cases = [];
foreach ($matrix as $role => $allowed) {
    foreach ($permissions as $permission) {
        $cases[$role.' '.$permission] = [$role, $permission, in_array($permission, $allowed, true)];
    }
}

it('enforces the complete resource and action permission matrix', function (string $role, string $permission, bool $allowed) {
    [$owner, $organization] = tenantWorkspace();
    $user = $owner;
    if ($role !== 'owner') {
        $membership = tenantMember($owner, $organization, RoleName::from($role));
        $user = $membership->user()->firstOrFail();
    }
    tenantActor($user, $organization);

    expect(Gate::forUser($user)->allows('tenant.permission', $permission))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('tenant.permission', 'unknown.permission'))->toBeFalse();
})->with($cases);

$pages = [];
foreach ($matrix as $role => $allowed) {
    foreach (['dashboard' => 'dashboard.view', 'organization.show' => 'organizations.view', 'memberships.index' => 'memberships.view', 'roles.index' => 'roles.view', 'permissions.index' => 'permissions.view'] as $route => $permission) {
        $pages[$role.' '.$route] = [$role, $route, in_array($permission, $allowed, true) ? 200 : 403];
    }
}
it('protects the HTTP interfaces with the same permissions', function (string $role, string $route, int $status) {
    [$owner, $organization] = tenantWorkspace();
    $user = $role === 'owner' ? $owner : tenantMember($owner, $organization, RoleName::from($role))->user()->firstOrFail();
    tenantActor($user, $organization);

    $this->get(route($route))->assertStatus($status);
})->with($pages);

it('only permits owners and admins to rename the active organization', function (string $role, bool $allowed) {
    [$owner, $organization] = tenantWorkspace();
    $user = $role === 'owner' ? $owner : tenantMember($owner, $organization, RoleName::from($role))->user()->firstOrFail();
    tenantActor($user, $organization);

    if (! $allowed) {
        expect(fn () => app(UpdateOrganization::class)($user, 'Changed'))->toThrow(AuthorizationException::class);
    } else {
        app(UpdateOrganization::class)($user, 'Changed');
    }
    expect($organization->refresh()->name)->toBe($allowed ? 'Changed' : 'Test workspace');
})->with([['owner', true], ['admin', true], ['manager', false], ['support', false], ['viewer', false]]);
