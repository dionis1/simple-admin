<?php

namespace App\Actions\Structure;

use App\Enums\AccessStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignTeamMember
{
    public function __invoke(User $actor, int $teamId, int $membershipId, bool $remove = false): void
    {
        DB::transaction(function () use ($actor, $teamId, $membershipId, $remove) {
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $team = Team::where('organization_id', $org)->findOrFail($teamId);
            Gate::forUser($actor)->authorize('update', $team);
            $query = Membership::where('organization_id', $org);
            if (! $remove) {
                $query->where('status', AccessStatus::Active)->whereHas('user', fn ($q) => $q->where('status', AccessStatus::Active));
            }
            $query->findOrFail($membershipId);
            if ($remove && $team->owner_membership_id === $membershipId) {
                throw ValidationException::withMessages(['team' => __('Transfer team ownership first.')]);
            }
            if ($remove) {
                TeamMembership::where('team_id', $teamId)->where('membership_id', $membershipId)->delete();
            } elseif (! TeamMembership::where('team_id', $teamId)->where('membership_id', $membershipId)->exists()) {
                $link = new TeamMembership;
                $link->forceFill(['organization_id' => $org, 'team_id' => $teamId, 'membership_id' => $membershipId])->save();
            }
        });
    }
}
