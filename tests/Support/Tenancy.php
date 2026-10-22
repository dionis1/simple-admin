<?php

use App\Actions\Memberships\AddMembership;
use App\Actions\Organizations\CreateOrganization;
use App\Enums\RoleName;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;

/** @return array{User, Organization} */
function tenantWorkspace(?User $owner = null, string $name = 'Test workspace'): array
{
    $owner ??= User::factory()->create();
    $organization = app(CreateOrganization::class)($owner, $name);

    return [$owner, $organization];
}

function tenantRole(Organization $organization, RoleName $name): Role
{
    return $organization->roles()->where('name', $name)->firstOrFail();
}

function tenantActor(User $user, Organization $organization): void
{
    test()->actingAs($user)->withSession([TenantContext::SESSION_KEY => $organization->id]);
}

function tenantMember(User $owner, Organization $organization, RoleName $role, ?User $user = null): Membership
{
    $user ??= User::factory()->create();
    tenantActor($owner, $organization);

    return app(AddMembership::class)($owner, $user->email, tenantRole($organization, $role)->id);
}
