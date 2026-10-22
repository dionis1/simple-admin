<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\User;
use App\Tenancy\TenantContext;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->allows($user, PermissionKey::ViewPermissions);
    }
}
