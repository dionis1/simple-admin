<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\RoleAssignment;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueInvitation
{
    public function __invoke(User $actor, string $email, int $roleId): Invitation
    {
        return DB::transaction(function () use ($actor, $email, $roleId) {
            $organization = Organization::query()->lockForUpdate()->findOrFail(app(TenantContext::class)->resolve($actor)->organization_id);
            Gate::forUser($actor)->authorize('create', Invitation::class);
            $role = $organization->roles()->findOrFail($roleId);
            Gate::forUser($actor)->authorize('create', [RoleAssignment::class, $role]);
            $email = strtolower(trim($email));
            Validator::make(compact('email'), ['email' => ['required', 'email', 'max:255']])->validate();
            if (Membership::where('organization_id', $organization->id)->whereHas('user', fn ($q) => $q->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => __('This account already has a membership. Use lifecycle management instead.')]);
            }
            Invitation::where('organization_id', $organization->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $token = Str::random(64);
            $invitation = new Invitation;
            $invitation->forceFill(['organization_id' => $organization->id, 'role_id' => $role->id, 'inviter_id' => $actor->id, 'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48)])->save();
            Notification::route('mail', $email)->notify((new OrganizationInvitation($token, $organization->name))->locale($actor->locale));

            return $invitation;
        });
    }
}
