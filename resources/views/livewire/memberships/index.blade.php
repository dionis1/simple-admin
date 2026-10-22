<div class="mx-auto w-full max-w-5xl space-y-6">
    <header><flux:heading size="xl" level="1">{{ __('Memberships') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Add verified existing accounts and manage access in this organization.') }}</flux:text></header>
    @if (session('message')) <flux:text role="status">{{ session('message') }}</flux:text> @endif
    @error('membership') <flux:text role="alert">{{ $message }}</flux:text> @enderror
    @can('create', \App\Models\Membership::class)
        <form wire:submit="add" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 sm:grid-cols-2">
            <flux:input wire:model="email" :label="__('Existing account email')" type="email" required />
            <flux:select wire:model="roleId" :label="__('Role')" required>
                <option value="">{{ __('Choose role') }}</option>
                @foreach ($roles as $role)
                    @can('create', [\App\Models\RoleAssignment::class, $role])
                        <option value="{{ $role->id }}">{{ ucfirst($role->name->value) }}</option>
                    @endcan
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Add membership') }}</flux:button>
        </form>
    @endcan
    @if ($editingId)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:heading level="2">{{ __('Edit membership') }}</flux:heading>
            <flux:select wire:model="editRoleId" :label="__('Assigned role')" required>
                @foreach ($roles as $role)
                    @can('create', [\App\Models\RoleAssignment::class, $role])
                        <option value="{{ $role->id }}">{{ ucfirst($role->name->value) }}</option>
                    @endcan
                @endforeach
            </flux:select>
            <flux:select wire:model="editStatus" :label="__('Membership status')" required>
                <option value="active">{{ __('Active') }}</option><option value="suspended">{{ __('Suspended') }}</option>
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Save membership') }}</flux:button>
        </form>
    @endif
    <div class="space-y-3">
        @foreach ($memberships as $membership)
            <article wire:key="membership-{{ $membership->id }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="min-w-0"><flux:heading level="2">{{ $membership->user->name }}</flux:heading>
                    <flux:text class="break-all">{{ $membership->user->email }}</flux:text>
                    <flux:text>{{ ucfirst($membership->roleAssignment?->role->name->value ?? 'unassigned') }} · {{ $membership->status->value }}</flux:text></div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $membership)<flux:button wire:click="edit({{ $membership->id }})">{{ __('Edit') }}</flux:button>@endcan
                    @can('delete', $membership)<flux:button variant="danger" wire:click="remove({{ $membership->id }})" wire:confirm="{{ __('Remove this membership?') }}">{{ __('Remove') }}</flux:button>@endcan
                </div>
            </article>
        @endforeach
    </div>
    {{ $memberships->links() }}
</div>
