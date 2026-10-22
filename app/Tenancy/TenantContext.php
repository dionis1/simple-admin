<?php

namespace App\Tenancy;

use App\Enums\AccessStatus;
use App\Enums\PermissionKey;
use App\Models\Membership;
use App\Models\User;

class TenantContext
{
    public const SESSION_KEY = 'active_organization_id';

    public function current(User $user): ?Membership
    {
        $id = session(self::SESSION_KEY);

        if (! $user->hasVerifiedEmail() || ! is_int($id) || $id < 1) {
            return null;
        }

        // Never reuse a hydrated component, cached relationship, or browser permission list.
        return $user->memberships()->where('organization_id', $id)
            ->where('status', AccessStatus::Active)
            ->whereHas('organization', fn ($query) => $query->where('status', AccessStatus::Active))
            ->whereHas('roleAssignment.role')->first();
    }

    public function resolve(User $user): Membership
    {
        $membership = $this->current($user);
        abort_unless($membership !== null, 403, 'Choose an organization with an active membership.');

        return $membership;
    }

    public function allows(User $user, PermissionKey|string $permission): bool
    {
        $membership = $this->current($user);
        $key = $permission instanceof PermissionKey ? $permission->value : $permission;

        return $membership?->assignedRole()?->permissions()->where('key', $key)->exists() ?? false;
    }
}
