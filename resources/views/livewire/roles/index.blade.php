<div class="mx-auto w-full max-w-5xl space-y-6">
    <header><flux:heading size="xl" level="1">{{ __('Organization roles') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Built-in roles have fixed permissions. Owners manage all roles; admins can assign and manage roles below Admin. Your own role cannot be changed by you.') }}</flux:text></header>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($roles as $role)
            <article wire:key="role-{{ $role->id }}" class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading level="2">{{ __(ucfirst($role->name->value)) }}</flux:heading>
                <ul class="mt-3 space-y-1 text-sm">
                    @foreach ($role->permissions as $permission)<li>{{ $permission->key }}</li>@endforeach
                </ul>
            </article>
        @endforeach
    </div>
</div>
