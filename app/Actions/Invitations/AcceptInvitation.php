<?php

namespace App\Actions\Invitations;

use App\Enums\AccessStatus;
use App\Enums\RoleName;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\SecurityNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function preview(User $actor, string $token): Invitation
    {
        abort_unless($actor->hasActiveVerifiedIdentity(), 403);
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->firstOrFail();
        abort_unless(hash_equals($invitation->email, strtolower($actor->email)), 403);
        abort_if($invitation->accepted_at || $invitation->revoked_at || $invitation->expires_at->isPast(), 410, __('This invitation is no longer available.'));

        return $invitation;
    }

    public function __invoke(User $actor, string $token): Membership
    {
        return DB::transaction(function () use ($actor, $token) {
            $initial = $this->preview($actor, $token);
            Organization::query()->where('status', AccessStatus::Active)->lockForUpdate()->findOrFail($initial->organization_id);
            $invitation = $this->preview($actor, $token);
            $inviter = Membership::where('organization_id', $invitation->organization_id)->where('user_id', $invitation->inviter_id)
                ->where('status', AccessStatus::Active)->whereHas('user', fn ($q) => $q->where('status', AccessStatus::Active)->whereNotNull('email_verified_at'))->first();
            $role = Role::where('organization_id', $invitation->organization_id)->findOrFail($invitation->role_id);
            $inviterRole = $inviter?->assignedRole();
            abort_unless($inviterRole && ($inviterRole->name === RoleName::Owner || $inviterRole->name->rank() > $role->name->rank())
                && $inviterRole->permissions()->where('key', 'invitations.manage')->exists()
                && $inviterRole->permissions()->where('key', 'roles.assign')->exists(), 403);
            $user = User::query()->where('status', AccessStatus::Active)->lockForUpdate()->findOrFail($actor->id);
            abort_unless($user->hasVerifiedEmail() && hash_equals($invitation->email, strtolower($user->email)), 403);
            if (Membership::where('organization_id', $invitation->organization_id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['invitation' => __('This account already has a membership. Use lifecycle management instead.')]);
            }
            $membership = new Membership;
            $membership->forceFill(['organization_id' => $invitation->organization_id, 'user_id' => $user->id, 'status' => AccessStatus::Active])->save();
            $assignment = new RoleAssignment;
            $assignment->forceFill(['organization_id' => $invitation->organization_id, 'membership_id' => $membership->id, 'role_id' => $role->id])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();
            $user->notify(new SecurityNotice('Your organization invitation was accepted.'));

            return $membership;
        });
    }
}
