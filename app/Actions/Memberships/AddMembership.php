<?php

namespace App\Actions\Memberships;

use App\Enums\AccessStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddMembership
{
    public function __invoke(User $actor, string $email, int $roleId): Membership
    {
        return DB::transaction(function () use ($actor, $email, $roleId) {
            $id = app(TenantContext::class)->resolve($actor)->organization_id;
            $organization = Organization::query()->lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('create', Membership::class);
            $role = $organization->roles()->findOrFail($roleId);
            Gate::forUser($actor)->authorize('create', [RoleAssignment::class, $role]);
            Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->validate();
            $user = User::query()->where('email', strtolower(trim($email)))->whereNotNull('email_verified_at')->where('status', AccessStatus::Active)->lockForUpdate()->first();

            if (! $user || $organization->memberships()->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => __('Choose a verified existing account that is not already a member.')]);
            }

            $membership = new Membership;
            $membership->forceFill(['organization_id' => $organization->id, 'user_id' => $user->id, 'status' => AccessStatus::Active])->save();
            $assignment = new RoleAssignment;
            $assignment->forceFill(['organization_id' => $organization->id, 'membership_id' => $membership->id, 'role_id' => $role->id])->save();

            return $membership;
        });
    }
}
