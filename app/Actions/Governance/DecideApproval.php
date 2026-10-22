<?php

namespace App\Actions\Governance;

use App\Actions\Security\ConfirmPassword;
use App\Enums\PermissionKey;
use App\Governance\Audit;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PersonalAccessToken;
use App\Models\SupportGrant;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DecideApproval
{
    public function __invoke(User $reviewer, int $id, bool $approve, string $password): void
    {
        app(ConfirmPassword::class)($reviewer, $password);
        DB::transaction(function () use ($reviewer, $id, $approve) {
            $context = app(TenantContext::class);
            $org = $context->resolve($reviewer)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            $request = ApprovalRequest::where('organization_id', $org)->lockForUpdate()->findOrFail($id);
            Gate::forUser($reviewer)->authorize('review', $request);
            abort_unless($request->state === 'pending', 409);
            if ($request->expires_at->isPast()) {
                $request->forceFill(['state' => 'expired'])->save();

                return;
            }
            if (! $approve) {
                $request->forceFill(['state' => 'rejected', 'reviewer_id' => $reviewer->id, 'decided_at' => now()])->save();

                return;
            }
            // Revalidate the requester under the same organization lock; no stale authority.
            $requester = User::findOrFail($request->requester_id);
            Gate::forUser($requester)->authorize('tenant.permission', $request->operation === 'support_access' ? PermissionKey::RequestSupport : PermissionKey::RequestApproval);
            $request->forceFill(['state' => 'approved', 'reviewer_id' => $reviewer->id, 'decided_at' => now()])->save();
            if ($request->operation === 'revoke_org_tokens') {
                $count = PersonalAccessToken::where('organization_id', $org)->delete();
                app(Audit::class)->record('tokens.organization_revoked', $org, 'Organization', $org, ['count' => $count, 'approval_id' => $request->id], $reviewer);
            } else {
                $target = Membership::where('organization_id', $org)->findOrFail($request->payload['target_membership_id']);
                app(RequestApproval::class)->assertSupportTarget($requester, $target);
                $grant = new SupportGrant;
                $grant->forceFill(['organization_id' => $org, 'approval_request_id' => $request->id, 'actor_id' => $requester->id, 'target_membership_id' => $target->id, 'expires_at' => now()->addMinutes(15)])->save();
            }
            $requester->notify(new SecurityNotice('A high-risk operation you requested was approved and executed.'));
            $request->forceFill(['state' => 'executed', 'executed_at' => now()])->save();
        });
    }
}
