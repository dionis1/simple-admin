<?php

namespace App\Actions\Governance;

use App\Actions\Security\ConfirmPassword;
use App\Enums\PermissionKey;
use App\Governance\Audit;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SupportGrant;
use App\Models\User;
use App\Notifications\SecurityNotice;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class SupportAccess
{
    public const SESSION_KEY = 'support_grant_id';

    public function start(User $actor, int $id, string $password, string $code): void
    {
        abort_if(session()->has(self::SESSION_KEY), 403);
        app(ConfirmPassword::class)($actor, $password);
        DB::transaction(function () use ($actor, $id, $code) {
            $user = User::query()->lockForUpdate()->findOrFail($actor->id);
            $org = app(TenantContext::class)->resolve($user)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            Gate::forUser($user)->authorize('tenant.permission', PermissionKey::RequestSupport);
            $grant = SupportGrant::where('organization_id', $org)->where('actor_id', $user->id)->lockForUpdate()->findOrFail($id);
            abort_unless($grant->started_at === null && $grant->ended_at === null && $grant->expires_at->isFuture(), 403);
            $target = $this->validateGrant($user, $grant);
            $limiter = 'support-step-up:'.$user->id;
            abort_if(RateLimiter::tooManyAttempts($limiter, 5), 429);
            RateLimiter::hit($limiter, 60);
            abort_unless($user->two_factor_confirmed_at !== null && $user->two_factor_secret !== null
                && preg_match('/^\d{6}$/D', $code) === 1
                && app(TwoFactorAuthenticationProvider::class)->verify(decrypt($user->two_factor_secret), $code), 403, 'Confirm your TOTP code before starting support access.');
            RateLimiter::clear($limiter);
            session()->regenerate();
            $grant->forceFill(['session_hash' => $this->sessionHash(), 'started_at' => now()])->save();
            session()->put(self::SESSION_KEY, $grant->id);
            $target->user()->firstOrFail()->notify(new SecurityNotice('Read-only support access to your organization view was started.'));
            app(Audit::class)->record('support.started', $org, 'SupportGrant', $grant->id, [], $user);
        });
    }

    public function validateGrant(User $actor, SupportGrant $grant): Membership
    {
        // Resolve real support operator even while a read-only subject is set.
        abort_unless($actor->hasActiveVerifiedIdentity()
            && $grant->actor_id === $actor->id && $grant->expires_at->isFuture() && $grant->ended_at === null, 403);
        $permission = $actor->memberships()->where('organization_id', $grant->organization_id)->where('status', 'active')
            ->whereHas('organization', fn ($q) => $q->where('status', 'active'))
            ->whereHas('roleAssignment.role.permissions', fn ($q) => $q->where('key', PermissionKey::RequestSupport->value))->exists();
        abort_unless($permission, 403);
        $approval = ApprovalRequest::findOrFail($grant->approval_request_id);
        $reviewer = User::findOrFail($approval->reviewer_id);
        abort_unless($approval->state === 'executed' && $approval->requester_id === $actor->id
            && $reviewer->hasActiveVerifiedIdentity()
            && $reviewer->memberships()->where('organization_id', $grant->organization_id)->where('status', 'active')
                ->whereHas('roleAssignment.role.permissions', fn ($q) => $q->where('key', PermissionKey::ReviewApproval->value))->exists(), 403);
        $target = Membership::where('organization_id', $grant->organization_id)->findOrFail($grant->target_membership_id);
        app(RequestApproval::class)->assertSupportTarget($actor, $target);
        abort_unless(($approval->payload['target_user_id'] ?? null) === $target->user_id, 403);

        return $target;
    }

    public function stop(User $actor): void
    {
        $id = session()->get(self::SESSION_KEY);
        $id = is_int($id) ? $id : 0;
        DB::transaction(function () use ($actor, $id) {
            $grant = SupportGrant::where('actor_id', $actor->id)->lockForUpdate()->find($id);
            if ($grant && $grant->ended_at === null) {
                $grant->forceFill(['ended_at' => now()])->save();
                app(Audit::class)->record('support.stopped', $grant->organization_id, 'SupportGrant', $grant->id, [], $actor);
            }
        });
        session()->forget(self::SESSION_KEY);
        session()->regenerate();
    }

    public function sessionHash(): string
    {
        return hash_hmac('sha256', session()->getId(), (string) config('app.key'));
    }
}
