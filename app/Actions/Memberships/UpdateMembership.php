<?php

namespace App\Actions\Memberships;

use App\Enums\AccessStatus;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\OwnerProtection;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateMembership
{
    public function __invoke(User $actor, int $membershipId, int $roleId, string $status): void
    {
        DB::transaction(function () use ($actor, $membershipId, $roleId, $status) {
            $id = app(TenantContext::class)->resolve($actor)->organization_id;
            $organization = Organization::query()->lockForUpdate()->findOrFail($id);
            $target = $organization->memberships()->findOrFail($membershipId);
            Gate::forUser($actor)->authorize('update', $target);
            $role = $organization->roles()->findOrFail($roleId);
            $currentRole = $target->assignedRole();
            abort_unless($currentRole !== null, 403);

            if ($currentRole->id !== $role->id) {
                abort_if($target->user_id === $actor->id, 403, 'You cannot change your own role.');
                Gate::forUser($actor)->authorize('create', [RoleAssignment::class, $role]);
            }

            Validator::make(['status' => $status], ['status' => [Rule::enum(AccessStatus::class)]])->validate();
            app(OwnerProtection::class)->ensureMayChange($target, $role->name, AccessStatus::from($status));
            $changed = $target->status->value !== $status || $currentRole->id !== $role->id;
            $target->forceFill(['status' => $status])->save();
            $target->roleAssignment()->firstOrFail()->forceFill(['role_id' => $role->id])->save();
            if ($status !== 'active' || $currentRole->id !== $role->id) {
                PersonalAccessToken::where('tokenable_id', $target->user_id)->where('tokenable_type', User::class)->where('organization_id', $id)->delete();
            }
            if ($changed) {
                $target->user()->firstOrFail()->notify(new SecurityNotice('Your organization membership access changed.'));
            }
        });
    }
}
