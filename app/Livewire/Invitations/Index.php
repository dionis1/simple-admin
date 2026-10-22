<?php

namespace App\Livewire\Invitations;

use App\Actions\Invitations\IssueInvitation;
use App\Actions\Invitations\RevokeInvitation;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\Invitation;
use App\Models\Role;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ResolvesUser, WithPagination;

    public string $email = '';

    public string $roleId = '';

    public string $search = '';

    public string $state = 'pending';

    public string $sort = 'newest';

    public function issue(IssueInvitation $issue): void
    {
        $this->resetValidation();
        Gate::authorize('create', Invitation::class);
        $this->validate(['roleId' => ['required', 'integer', 'min:1']]);
        $issue($this->actor(), $this->email, (int) $this->roleId);
        $this->reset('email', 'roleId');
        session()->flash('message', __('Invitation queued. Sending again replaces the old link.'));
    }

    public function revoke(int $id, RevokeInvitation $revoke): void
    {
        $this->resetValidation();
        $revoke($this->actor(), $id);
        session()->flash('message', __('Invitation revoked.'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Invitation::class);
        abort_unless(in_array($this->state, ['pending', 'expired', 'accepted', 'revoked', 'all'], true) && in_array($this->sort, ['newest', 'oldest'], true), 422);
        $org = app(TenantContext::class)->resolve($this->actor())->organization_id;
        $query = Invitation::where('organization_id', $org)->where('email', 'ilike', '%'.substr($this->search, 0, 100).'%');
        match ($this->state) {
            'pending' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now()),
            'expired' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now()),
            'accepted' => $query->whereNotNull('accepted_at'),
            'revoked' => $query->whereNotNull('revoked_at'),
            default => $query,
        };

        return view('livewire.invitations.index', ['invitations' => $query->orderBy('id', $this->sort === 'newest' ? 'desc' : 'asc')->paginate(20), 'roles' => Role::where('organization_id', $org)->get()])->title(__('Invitations'));
    }
}
