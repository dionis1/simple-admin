<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;

class RoleAssignmentPolicy
{
    public function create(User $user, Role $role): bool
    {
        $context = app(TenantContext::class);
        $actor = $context->current($user);
        $actorRole = $actor?->assignedRole();

        return $actor?->organization_id === $role->organization_id
            && $actorRole !== null
            && $context->allows($user, PermissionKey::AssignRoles)
            && ($actorRole->name === RoleName::Owner || $actorRole->name->rank() > $role->name->rank());
    }
}
