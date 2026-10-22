<div class="mx-auto w-full max-w-5xl space-y-6">
    <header><flux:heading size="xl" level="1">{{ __('Permissions') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Resource and action permissions granted to roles in the active organization. This catalog is read-only.') }}</flux:text></header>
    <div class="space-y-3">
        @foreach ($permissions as $permission)
            <article wire:key="permission-{{ $permission->id }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading level="2">{{ $permission->resource }} / {{ $permission->action }}</flux:heading>
                <flux:text>{{ $permission->roles->map(fn ($role) => ucfirst($role->name->value))->join(', ') }}</flux:text>
            </article>
        @endforeach
    </div>
</div>
