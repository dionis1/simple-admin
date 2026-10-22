<?php

namespace App\Policies;

use App\Enums\PermissionKey;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Tenancy\TenantContext;

class ApprovalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        $context = app(TenantContext::class);

        return $context->allows($user, PermissionKey::RequestApproval) || $context->allows($user, PermissionKey::RequestSupport);
    }

    public function review(User $user, ApprovalRequest $approval): bool
    {
        $context = app(TenantContext::class);

        return $approval->organization_id === $context->current($user)?->organization_id
            && $approval->requester_id !== $user->id
            && ($approval->payload['target_user_id'] ?? null) !== $user->id
            && $context->allows($user, PermissionKey::ReviewApproval);
    }
}
