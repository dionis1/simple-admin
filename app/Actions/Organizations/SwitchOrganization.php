<?php

namespace App\Actions\Organizations;

use App\Enums\AccessStatus;
use App\Models\User;
use App\Tenancy\TenantContext;

class SwitchOrganization
{
    public function __invoke(User $actor, int $organizationId): void
    {
        abort_unless($actor->hasVerifiedEmail(), 403);
        $membership = $actor->memberships()->where('organization_id', $organizationId)
            ->where('status', AccessStatus::Active)
            ->whereHas('organization', fn ($query) => $query->where('status', AccessStatus::Active))
            ->whereHas('roleAssignment.role')->first();
        abort_unless($membership !== null, 403, 'Organization is unavailable.');

        session()->put(TenantContext::SESSION_KEY, $membership->organization_id);
    }
}
