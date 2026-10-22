<?php

namespace App\Actions\Structure;

use App\Enums\AccessStatus;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveTeam
{
    public function __invoke(User $actor, int $id, string $name, int $ownerId, ?int $departmentId): Team
    {
        return DB::transaction(function () use ($actor, $id, $name, $ownerId, $departmentId) {
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $team = $id ? Team::where('organization_id', $org)->findOrFail($id) : new Team;
            Gate::forUser($actor)->authorize($id ? 'update' : 'create', $id ? $team : Team::class);
            Validator::make(['teamName' => trim($name)], ['teamName' => ['required', 'string', 'max:120', Rule::unique('teams', 'name')->where('organization_id', $org)->ignore($id)]])->validate();
            Membership::where('organization_id', $org)->where('status', AccessStatus::Active)->whereHas('user', fn ($q) => $q->where('status', AccessStatus::Active))->findOrFail($ownerId);
            if ($departmentId) {
                Department::where('organization_id', $org)->findOrFail($departmentId);
            }
            $team->forceFill(['organization_id' => $org, 'name' => trim($name), 'owner_membership_id' => $ownerId, 'department_id' => $departmentId])->save();
            if (! TeamMembership::where('team_id', $team->id)->where('membership_id', $ownerId)->exists()) {
                $link = new TeamMembership;
                $link->forceFill(['organization_id' => $org, 'team_id' => $team->id, 'membership_id' => $ownerId])->save();
            }

            return $team;
        });
    }
}
