<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeInvitation
{
    public function __invoke(User $actor, int $id): void
    {
        DB::transaction(function () use ($actor, $id) {
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $invitation = Invitation::where('organization_id', $org)->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $invitation);
            if (! $invitation->accepted_at) {
                $invitation->forceFill(['revoked_at' => now()])->save();
            }
        });
    }
}
