<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\DecideApproval;
use App\Actions\Governance\RequestApproval;
use App\Actions\Governance\SupportAccess;
use App\Enums\PermissionKey;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\ApprovalRequest;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\SupportGrant;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Approvals extends Component
{
    use ResolvesUser, WithPagination;

    public string $operation = 'support_access';

    public string $reason = '';

    public string $targetId = '';

    public string $currentPassword = '';

    public string $totp = '';

    public string $state = '';

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function requestApproval(): void
    {
        $this->validate(['targetId' => ['nullable', 'integer', 'min:1']]);
        app(RequestApproval::class)($this->actor(), $this->operation, $this->reason, $this->targetId === '' ? null : (int) $this->targetId);
        $this->reset('reason', 'targetId');
        session()->flash('message', __('Approval requested.'));
    }

    public function decide(int $id, bool $approve): void
    {
        try {
            app(DecideApproval::class)($this->actor(), $id, $approve, $this->currentPassword);
            session()->flash('message', __('Approval decision recorded.'));
        } finally {
            $this->reset('currentPassword', 'totp');
        }
    }

    public function cancel(int $id): void
    {
        DB::transaction(function () use ($id) {
            $actor = $this->actor();
            $org = app(TenantContext::class)->resolve($actor)->organization_id;
            Organization::query()->lockForUpdate()->findOrFail($org);
            Gate::forUser($actor)->authorize('viewAny', ApprovalRequest::class);
            $request = ApprovalRequest::where('organization_id', $org)->where('requester_id', $actor->id)->lockForUpdate()->findOrFail($id);
            abort_unless($request->state === 'pending', 409);
            $request->forceFill(['state' => $request->expires_at->isPast() ? 'expired' : 'cancelled'])->save();
        });
    }

    public function start(int $id): void
    {
        try {
            app(SupportAccess::class)->start($this->actor(), $id, $this->currentPassword, $this->totp);
            $this->redirectRoute('dashboard');
        } finally {
            $this->reset('currentPassword', 'totp');
        }
    }

    public function render(): View
    {
        $actor = $this->actor();
        Gate::forUser($actor)->authorize('viewAny', ApprovalRequest::class);
        abort_unless(in_array($this->state, ['', 'pending', 'executed', 'rejected', 'cancelled', 'expired'], true), 422);
        $context = app(TenantContext::class);
        $org = $context->resolve($actor)->organization_id;
        $requests = ApprovalRequest::where('organization_id', $org);
        if (! $context->allows($actor, PermissionKey::ReviewApproval)) {
            $requests->where('requester_id', $actor->id);
        }
        if ($this->state === 'expired') {
            $requests->where(fn ($q) => $q->where('state', 'expired')->orWhere(fn ($q) => $q->where('state', 'pending')->where('expires_at', '<=', now())));
        } elseif ($this->state !== '') {
            $requests->where('state', $this->state);
            if ($this->state === 'pending') {
                $requests->where('expires_at', '>', now());
            }
        }

        return view('livewire.governance.approvals', [
            'requests' => $requests->latest('id')->paginate(20),
            'targets' => Membership::where('organization_id', $org)->where('status', 'active')->with('user')->get(),
            'grants' => SupportGrant::where('organization_id', $org)->where('actor_id', $actor->id)->whereNull('started_at')->whereNull('ended_at')->where('expires_at', '>', now())->get(),
        ])->title(__('Approvals'));
    }
}
