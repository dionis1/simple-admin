<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Tenancy\TenantContext;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->allows($user, 'teams.view');
    }

    public function create(User $user): bool
    {
        return app(TenantContext::class)->allows($user, 'teams.manage');
    }

    public function update(User $user, Team $team): bool
    {
        return app(TenantContext::class)->current($user)?->organization_id === $team->organization_id && $this->create($user);
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->update($user, $team);
    }
}
