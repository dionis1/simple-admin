<div class="mx-auto w-full max-w-5xl space-y-6">
    <header><flux:heading size="xl" level="1">{{ __('Memberships') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Add verified existing accounts and manage access in this organization.') }}</flux:text></header>
    @if (session('message')) <flux:text role="status">{{ __(session('message')) }}</flux:text> @endif
    @error('membership') <flux:text role="alert">{{ $message }}</flux:text> @enderror
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search members')" />
        <flux:select wire:model.live="statusFilter" :label="__('Status filter')"><option value="">{{ __('All statuses') }}</option>@foreach(['active','suspended','deactivated'] as $status)<option value="{{ $status }}">{{ __(ucfirst($status)) }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="sort" :label="__('Sort')"><option value="newest">{{ __('Newest first') }}</option><option value="oldest">{{ __('Oldest first') }}</option><option value="status">{{ __('Status') }}</option></flux:select>
    </div>
    @can('create', \App\Models\Membership::class)
        <form wire:submit="bulk" class="flex flex-wrap items-end gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:select wire:model="bulkStatus" :label="__('Bulk membership action')"><option value="suspended">{{ __('Suspend') }}</option><option value="deactivated">{{ __('Deactivate') }}</option><option value="active">{{ __('Reactivate') }}</option></flux:select>
            <flux:text>{{ __('Selected: :count',['count'=>count($selected)]) }}</flux:text>
            <flux:button type="submit" :disabled="count($selected) === 0" wire:confirm="{{ __('Apply this change to all selected memberships?') }}">{{ __('Apply to selected') }}</flux:button>
            @error('ids')<p role="alert">{{ $message }}</p>@enderror
        </form>
    @endcan
    @can('create', \App\Models\Membership::class)
        <form wire:submit="add" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 sm:grid-cols-2">
            <flux:input wire:model="email" :label="__('Existing account email')" type="email" required />
            <flux:select wire:model="roleId" :label="__('Role')" required>
                <option value="">{{ __('Choose role') }}</option>
                @foreach ($roles as $role)
                    @can('create', [\App\Models\RoleAssignment::class, $role])
                        <option value="{{ $role->id }}">{{ __(ucfirst($role->name->value)) }}</option>
                    @endcan
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Add membership') }}</flux:button>
        </form>
    @endcan
    @if ($editingId)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="2">{{ __('Edit membership') }}</flux:heading>
            <flux:input wire:model="displayName" :label="__('Organization display name')" maxlength="120" />
            <flux:input wire:model="jobTitle" :label="__('Job title')" maxlength="120" />
            <flux:select wire:model="memberDepartmentId" :label="__('Department')"><option value="">{{ __('No department') }}</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</flux:select>
            <flux:select wire:model="editRoleId" :label="__('Assigned role')" required>
                @foreach ($roles as $role)
                    @can('create', [\App\Models\RoleAssignment::class, $role])
                        <option value="{{ $role->id }}">{{ __(ucfirst($role->name->value)) }}</option>
                    @endcan
                @endforeach
            </flux:select>
            <flux:select wire:model="editStatus" :label="__('Membership status')" required>
                <option value="active">{{ __('Active') }}</option><option value="suspended">{{ __('Suspended') }}</option><option value="deactivated">{{ __('Deactivated') }}</option>
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Save membership') }}</flux:button>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($memberships as $membership)
            <article wire:key="membership-{{ $membership->id }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                @can('update',$membership)<flux:checkbox wire:model="selected" value="{{ $membership->id }}" :label="__('Select :name',['name'=>$membership->user->name])" />@endcan
                <div class="min-w-0"><flux:heading level="2">{{ $membership->display_name ?: $membership->user->name }}</flux:heading>
                    <flux:text class="break-all">{{ $membership->user->email }}</flux:text>
                    @if($membership->job_title)<flux:text>{{ $membership->job_title }}</flux:text>@endif
                    <flux:text>{{ __('Account status') }}: {{ __(ucfirst($membership->user->status->value)) }}</flux:text>
                    <flux:text>{{ __(ucfirst($membership->roleAssignment?->role->name->value ?? 'unassigned')) }} · {{ __(ucfirst($membership->status->value)) }}</flux:text></div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $membership)<flux:button wire:click="edit({{ $membership->id }})">{{ __('Edit') }}</flux:button>@endcan
                    @can('delete', $membership)<flux:button variant="danger" wire:click="remove({{ $membership->id }})" wire:confirm="{{ __('Remove this membership?') }}">{{ __('Remove') }}</flux:button>@endcan
                </div>
            </article>
        @endforeach
    </div>
    {{ $memberships->links() }}
</div>
