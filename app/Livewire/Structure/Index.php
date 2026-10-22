<?php

namespace App\Livewire\Structure;

use App\Actions\Structure\AssignTeamMember;
use App\Actions\Structure\DeleteStructure;
use App\Actions\Structure\SaveDepartment;
use App\Actions\Structure\SaveTeam;
use App\Enums\AccessStatus;
use App\Livewire\Concerns\ResolvesUser;
use App\Models\Department;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamMembership;
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

    public string $search = '';

    public string $departmentFilter = '';

    public string $sort = 'name';

    public string $departmentName = '';

    public int $departmentId = 0;

    public string $teamName = '';

    public int $teamId = 0;

    public string $ownerId = '';

    public string $teamDepartmentId = '';

    public string $assignmentTeam = '';

    public string $assignmentMember = '';

    private function organizationId(): int
    {
        return app(TenantContext::class)->resolve($this->actor())->organization_id;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->resetPage();
    }

    public function saveDepartment(SaveDepartment $save): void
    {
        $this->resetValidation();
        $save($this->actor(), $this->departmentId, $this->departmentName);
        $this->reset('departmentId', 'departmentName');
        session()->flash('message', __('Department saved.'));
    }

    public function editDepartment(int $id): void
    {
        $department = Department::where('organization_id', $this->organizationId())->findOrFail($id);
        Gate::authorize('update', $department);
        $this->departmentId = $id;
        $this->departmentName = $department->name;
    }

    public function saveTeam(SaveTeam $save): void
    {
        $this->resetValidation();
        $this->validate(['ownerId' => ['required', 'integer', 'min:1'], 'teamDepartmentId' => ['nullable', 'integer', 'min:1']]);
        $save($this->actor(), $this->teamId, $this->teamName, (int) $this->ownerId, $this->teamDepartmentId ? (int) $this->teamDepartmentId : null);
        $this->reset('teamId', 'teamName', 'ownerId', 'teamDepartmentId');
        session()->flash('message', __('Team saved.'));
    }

    public function editTeam(int $id): void
    {
        $team = Team::where('organization_id', $this->organizationId())->findOrFail($id);
        Gate::authorize('update', $team);
        $this->teamId = $id;
        $this->teamName = $team->name;
        $this->ownerId = (string) $team->owner_membership_id;
        $this->teamDepartmentId = (string) $team->department_id;
    }

    public function assign(AssignTeamMember $assign): void
    {
        $this->resetValidation();
        $this->validate(['assignmentTeam' => ['required', 'integer', 'min:1'], 'assignmentMember' => ['required', 'integer', 'min:1']]);
        $assign($this->actor(), (int) $this->assignmentTeam, (int) $this->assignmentMember);
        session()->flash('message', __('Team membership saved.'));
    }

    public function unassign(int $team, int $member, AssignTeamMember $assign): void
    {
        $assign($this->actor(), $team, $member, true);
    }

    public function delete(string $type, int $id, DeleteStructure $delete): void
    {
        $delete($this->actor(), $type, $id);
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Team::class);
        Gate::authorize('viewAny', Department::class);
        abort_unless(in_array($this->sort, ['name', 'newest'], true), 422);
        $org = $this->organizationId();
        $query = Team::where('organization_id', $org)->with(['owner.user', 'department'])->where('name', 'ilike', '%'.substr($this->search, 0, 100).'%');
        if ($this->departmentFilter !== '') {
            $query->where('department_id', (int) $this->departmentFilter);
        }

        return view('livewire.structure.index', [
            'teams' => $query->orderBy($this->sort === 'name' ? 'name' : 'id', $this->sort === 'name' ? 'asc' : 'desc')->paginate(12),
            'departments' => Department::where('organization_id', $org)->orderBy('name')->get(),
            'members' => Gate::allows('create', Team::class) ? Membership::where('organization_id', $org)->where('status', AccessStatus::Active)->whereHas('user', fn ($q) => $q->where('status', AccessStatus::Active))->with('user')->get() : collect(),
            'allTeams' => Gate::allows('create', Team::class) ? Team::where('organization_id', $org)->orderBy('name')->get() : collect(),
            'assignments' => TeamMembership::where('organization_id', $org)->with(['membership.user'])->get(),
        ])->title(__('Teams and departments'));
    }
}
