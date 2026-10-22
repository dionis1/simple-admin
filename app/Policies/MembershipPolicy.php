<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Enums\RoleName;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;

class MembershipPolicy
{
    public function __construct(private TenantContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->allows($user, PermissionKey::ViewMemberships);
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->context->current($user)?->organization_id === $membership->organization_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->context->allows($user, PermissionKey::CreateMemberships);
    }

    public function update(User $user, Membership $membership): bool
    {
        return $this->canManage($user, $membership, PermissionKey::UpdateMemberships);
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $this->canManage($user, $membership, PermissionKey::DeleteMemberships);
    }

    private function canManage(User $user, Membership $target, PermissionKey $permission): bool
    {
        $actor = $this->context->current($user);
        $actorRole = $actor?->assignedRole();
        $targetRole = $target->assignedRole();

        return $actor?->organization_id === $target->organization_id
            && $actorRole !== null && $targetRole !== null
            && $this->context->allows($user, $permission)
            && ($actorRole->name === RoleName::Owner || $actorRole->name->rank() > $targetRole->name->rank());
    }
}
