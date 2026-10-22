<?php

namespace App\Livewire\Governance;

use App\Enums\PermissionKey;
use App\Governance\Health;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    use ResolvesUser;

    public function render(): View
    {
        $actor = $this->actor();
        $context = app(TenantContext::class);
        Gate::forUser($actor)->authorize('tenant.permission', PermissionKey::ViewDashboard);
        $org = $context->resolve($actor)->organization_id;
        $canAudit = $context->allows($actor, PermissionKey::ViewAudit);
        $canOperate = $context->allows($actor, PermissionKey::ViewOperations);
        $canApprove = $context->allows($actor, PermissionKey::ReviewApproval);

        return view('livewire.governance.dashboard', [
            'activity' => $canAudit ? AuditEvent::where('organization_id', $org)->latest('id')->limit(8)->get() : collect(),
            'securityEvents' => $canAudit ? AuditEvent::where('organization_id', $org)->where(fn ($q) => $q->where('event', 'like', 'support.%')->orWhere('event', 'like', 'tokens.%')->orWhere('event', 'like', 'personal_access_token.%'))->where('occurred_at', '>', now()->subDay())->count() : null,
            'invitations' => $context->allows($actor, PermissionKey::ManageInvitations) ? Invitation::where('organization_id', $org)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count() : null,
            'approvals' => $canApprove ? ApprovalRequest::where('organization_id', $org)->where('state', 'pending')->where('expires_at', '>', now())->count() : null,
            'health' => $canOperate ? app(Health::class)->check() : null,
            'failures' => $canOperate ? DB::table('queue_incidents')->where('organization_id', $org)->orderByDesc('id')->limit(5)->get() : collect(),
        ])->title(__('Dashboard'));
    }
}
