<div class="mx-auto w-full max-w-6xl space-y-8">
    <header><flux:heading size="xl" level="1">{{ __('Teams and departments') }}</flux:heading><flux:text>{{ __('Organize workspaces and assign active members. Ownership must be transferred before access is removed.') }}</flux:text></header>
    @if(session('message'))<p role="status">{{ __(session('message')) }}</p>@endif
    @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="grid gap-6 lg:grid-cols-2">
        @can('create',\App\Models\Department::class)
        <form wire:submit="saveDepartment" class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading level="2">{{ $departmentId ? __('Edit department'):__('Create department') }}</flux:heading>
            <flux:input wire:model="departmentName" :label="__('Department name')" required maxlength="120" />
            <flux:button type="submit" variant="primary">{{ __('Save department') }}</flux:button>
        </form>@endcan
        @can('create',\App\Models\Team::class)
        <form wire:submit="saveTeam" class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading level="2">{{ $teamId ? __('Edit team'):__('Create team') }}</flux:heading>
            <flux:input wire:model="teamName" :label="__('Team name')" required maxlength="120" />
            <flux:select wire:model="ownerId" :label="__('Team owner')" required><option value="">{{ __('Choose member') }}</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->user->name }}</option>@endforeach</flux:select>
            <flux:select wire:model="teamDepartmentId" :label="__('Department')"><option value="">{{ __('No department') }}</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</flux:select>
            <flux:button type="submit" variant="primary">{{ __('Save team') }}</flux:button>
        </form>@endcan
    </div>
    <section class="space-y-3"><flux:heading size="lg" level="2">{{ __('Departments') }}</flux:heading>
        <div class="flex flex-wrap gap-3">@forelse($departments as $department)<article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" wire:key="department-{{ $department->id }}"><flux:heading>{{ $department->name }}</flux:heading>
            @can('update',$department)<div class="mt-3 flex gap-2"><flux:button size="sm" wire:click="editDepartment({{ $department->id }})">{{ __('Edit') }}</flux:button><flux:button size="sm" variant="danger" wire:click="delete('department',{{ $department->id }})" wire:confirm="{{ __('Delete this department? Teams and member profiles will be unassigned.') }}">{{ __('Delete') }}</flux:button></div>@endcan
        </article>@empty<flux:text>{{ __('No departments yet.') }}</flux:text>@endforelse</div>
    </section>
    @can('create',\App\Models\Team::class)
    <form wire:submit="assign" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 sm:grid-cols-3">
        <flux:select wire:model="assignmentTeam" :label="__('Team')" required><option value="">{{ __('Choose team') }}</option>@foreach($allTeams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</flux:select>
        <flux:select wire:model="assignmentMember" :label="__('Member')" required><option value="">{{ __('Choose member') }}</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->user->name }}</option>@endforeach</flux:select>
        <flux:button type="submit" class="self-end">{{ __('Assign member') }}</flux:button>
    </form>@endcan
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search teams')" />
        <flux:select wire:model.live="departmentFilter" :label="__('Department filter')"><option value="">{{ __('All departments') }}</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="sort" :label="__('Sort')"><option value="name">{{ __('Name') }}</option><option value="newest">{{ __('Newest first') }}</option></flux:select>
    </div>
    <div class="grid gap-5 lg:grid-cols-2">@forelse($teams as $team)
        <article class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" wire:key="team-{{ $team->id }}">
            <header><flux:heading size="lg" level="2">{{ $team->name }}</flux:heading><flux:text>{{ __('Department') }}: {{ $team->department?->name ?? __('No department') }} · {{ __('Owner') }}: {{ $team->owner->user->name }}</flux:text></header>
            @can('update',$team)<div class="flex gap-2"><flux:button wire:click="editTeam({{ $team->id }})">{{ __('Edit') }}</flux:button><flux:button variant="danger" wire:click="delete('team',{{ $team->id }})" wire:confirm="{{ __('Delete this team?') }}">{{ __('Delete') }}</flux:button></div>@endcan
            <ul class="space-y-2">@foreach($assignments->where('team_id',$team->id) as $assignment)<li class="flex flex-wrap justify-between gap-2"><span>{{ $assignment->membership->display_name ?: $assignment->membership->user->name }} · {{ __(ucfirst($assignment->membership->status->value)) }}</span>@if($team->owner_membership_id !== $assignment->membership_id)@can('update',$team)<flux:button size="sm" wire:click="unassign({{ $team->id }},{{ $assignment->membership_id }})" wire:confirm="{{ __('Remove this team assignment?') }}">{{ __('Remove') }}</flux:button>@endcan @endif</li>@endforeach</ul>
        </article>
    @empty<flux:text>{{ __('No teams found.') }}</flux:text>@endforelse</div>
    {{ $teams->links() }}
</div>
