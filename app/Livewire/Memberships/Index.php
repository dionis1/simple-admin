<?php

namespace App\Livewire\Memberships;

use App\Actions\Memberships\AddMembership;
use App\Actions\Memberships\RemoveMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\Membership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use ResolvesUser, WithPagination;

    public string $email = '';

    #[Validate('required|integer|min:1')]
    public string $roleId = '';

    #[Locked]
    public int $editingId = 0;

    #[Validate('required|integer|min:1')]
    public string $editRoleId = '';

    public string $editStatus = 'active';

    public function add(AddMembership $add): void
    {
        Gate::authorize('create', Membership::class);
        $this->validateOnly('roleId');
        $add($this->actor(), $this->email, (int) $this->roleId);
        $this->reset('email', 'roleId');
        session()->flash('message', 'Membership added.');
    }

    public function edit(int $id): void
    {
        $organization = app(TenantContext::class)->resolve($this->actor())->organization()->firstOrFail();
        $membership = $organization->memberships()->findOrFail($id);
        Gate::authorize('update', $membership);
        $this->editingId = $membership->id;
        $this->editRoleId = (string) $membership->assignedRole()?->id;
        $this->editStatus = $membership->status->value;
    }

    public function save(UpdateMembership $update): void
    {
        app(TenantContext::class)->resolve($this->actor());
        $this->validateOnly('editRoleId');
        $update($this->actor(), $this->editingId, (int) $this->editRoleId, $this->editStatus);
        $this->reset('editingId', 'editRoleId', 'editStatus');
        session()->flash('message', 'Membership updated.');
        $this->leaveIfRevoked();
    }

    public function remove(int $id, RemoveMembership $remove): void
    {
        $remove($this->actor(), $id);
        session()->flash('message', 'Membership removed.');
        $this->leaveIfRevoked();
    }

    private function leaveIfRevoked(): void
    {
        if (! app(TenantContext::class)->current($this->actor())) {
            $this->redirectRoute('organizations.index', navigate: true);
        }
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Membership::class);
        $organization = app(TenantContext::class)->resolve($this->actor())->organization()->firstOrFail();

        return view('livewire.memberships.index', [
            'memberships' => $organization->memberships()->with(['user', 'roleAssignment.role'])->orderBy('id')->paginate(20),
            'roles' => $organization->roles()->orderBy('id')->get(),
        ])->title('Memberships');
    }
}
