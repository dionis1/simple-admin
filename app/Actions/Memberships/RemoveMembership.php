<?php

namespace App\Actions\Memberships;

use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Tenancy\OwnerProtection;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveMembership
{
    public function __invoke(User $actor, int $membershipId): void
    {
        DB::transaction(function () use ($actor, $membershipId) {
            $id = app(TenantContext::class)->resolve($actor)->organization_id;
            $organization = Organization::query()->lockForUpdate()->findOrFail($id);
            $target = $organization->memberships()->findOrFail($membershipId);
            Gate::forUser($actor)->authorize('delete', $target);
            app(OwnerProtection::class)->ensureMayChange($target, null, null);
            PersonalAccessToken::where('tokenable_id', $target->user_id)->where('tokenable_type', User::class)->where('organization_id', $id)->delete();
            $target->delete();
        });
    }
}
