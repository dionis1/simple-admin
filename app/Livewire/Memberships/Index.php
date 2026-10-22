<?php

namespace App\Livewire\Memberships;

use App\Actions\Memberships\AddMembership;
use App\Actions\Memberships\BulkMembershipLifecycle;
use App\Actions\Memberships\EditMembershipProfile;
use App\Actions\Memberships\RemoveMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\Department;
use App\Models\Membership;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    public string $search = '';

    public string $statusFilter = '';

    public string $sort = 'newest';

    public string $memberDepartmentId = '';

    public string $displayName = '';

    public string $jobTitle = '';

    /** @var array<int,int|string> */
    public array $selected = [];

    public string $bulkStatus = 'suspended';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function bulk(BulkMembershipLifecycle $bulk): void
    {
        $this->resetValidation();
        $bulk($this->actor(), $this->selected, $this->bulkStatus);
        $this->reset('selected');
        session()->flash('message', __('Memberships updated.'));
        $this->leaveIfRevoked();
    }

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
        $this->resetValidation();
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
        $this->displayName = $membership->display_name ?? '';
        $this->jobTitle = $membership->job_title ?? '';
        $this->memberDepartmentId = (string) $membership->getAttribute('department_id');
    }

    public function save(UpdateMembership $update): void
    {
        $this->resetValidation();
        app(TenantContext::class)->resolve($this->actor());
        $this->validateOnly('editRoleId');
        $this->validate(['memberDepartmentId' => ['nullable', 'integer', 'min:1']]);
        DB::transaction(function () use ($update) {
            app(EditMembershipProfile::class)($this->actor(), $this->editingId, $this->displayName, $this->jobTitle, $this->memberDepartmentId ? (int) $this->memberDepartmentId : null);
            $update($this->actor(), $this->editingId, (int) $this->editRoleId, $this->editStatus);
        });
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

        abort_unless(in_array($this->statusFilter, ['', 'active', 'suspended', 'deactivated'], true) && in_array($this->sort, ['newest', 'oldest', 'status'], true), 422);
        $query = $organization->memberships()->with(['user', 'roleAssignment.role']);
        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }
        if ($this->search !== '') {
            $term = '%'.substr($this->search, 0, 100).'%';
            $query->where(fn ($q) => $q->where('display_name', 'ilike', $term)->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term)));
        }

        return view('livewire.memberships.index', [
            'memberships' => $query->orderBy($this->sort === 'status' ? 'status' : 'id', $this->sort === 'newest' ? 'desc' : 'asc')->paginate(20),
            'departments' => Department::where('organization_id', $organization->id)->orderBy('name')->get(),
            'roles' => $organization->roles()->orderBy('id')->get(),
        ])->title('Memberships');
    }
}
