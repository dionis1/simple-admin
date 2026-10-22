<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;

class OrganizationPolicy
{
    public function __construct(private TenantContext $context) {}

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->context->current($user)?->organization_id === $organization->id
            && $this->context->allows($user, PermissionKey::ViewOrganization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->context->current($user)?->organization_id === $organization->id
            && $this->context->allows($user, PermissionKey::UpdateOrganization);
    }
}
