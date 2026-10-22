<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->allows($user, 'invitations.manage');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        $role = Role::find($invitation->role_id);

        return app(TenantContext::class)->current($user)?->organization_id === $invitation->organization_id
            && $this->create($user) && $role !== null && Gate::forUser($user)->allows('create', [RoleAssignment::class, $role]);
    }
}
