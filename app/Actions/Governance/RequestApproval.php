<?php

namespace App\Actions\Governance;

use App\Enums\PermissionKey;
use App\Enums\RoleName;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RequestApproval
{
    public function __invoke(User $actor, string $operation, string $reason, ?int $targetId = null): ApprovalRequest
    {
        Validator::make(compact('operation', 'reason', 'targetId'), ['targetId' => ['nullable', Rule::requiredIf($operation === 'support_access'), 'integer', 'min:1'], 'operation' => [Rule::in(['revoke_org_tokens', 'support_access'])], 'reason' => ['required', 'string', 'min:10', 'max:500']])->validate();

        return DB::transaction(function () use ($actor, $operation, $reason, $targetId) {
            $context = app(TenantContext::class);
            $org = $context->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            Gate::forUser($actor)->authorize('tenant.permission', $operation === 'support_access' ? PermissionKey::RequestSupport : PermissionKey::RequestApproval);
            $payload = [];
            if ($operation === 'support_access') {
                $target = Membership::where('organization_id', $org)->findOrFail($targetId);
                $this->assertSupportTarget($actor, $target);
                $payload = ['target_membership_id' => $target->id, 'target_user_id' => $target->user_id];
            }
            $request = new ApprovalRequest;
            $request->forceFill(['organization_id' => $org, 'requester_id' => $actor->id, 'operation' => $operation, 'reason' => $reason, 'payload' => $payload, 'state' => 'pending', 'expires_at' => now()->addHours(24)])->save();

            return $request;
        });
    }

    public function assertSupportTarget(User $actor, Membership $target): void
    {
        $role = $target->assignedRole()?->name;
        abort_unless($target->user_id !== $actor->id && $target->status->value === 'active'
            && $target->user()->where('status', 'active')->whereNotNull('email_verified_at')->exists()
            && in_array($role, [RoleName::Manager, RoleName::Support, RoleName::Viewer], true), 403);
    }
}
