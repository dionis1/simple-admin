<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8">
        <header>
            <flux:heading size="xl" level="1">{{ __('Workspace overview') }}</flux:heading>
            <flux:text class="mt-2">{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</flux:text>
        </header>

        <section aria-label="{{ __('Foundation status') }}" class="grid gap-4 md:grid-cols-3">
            @foreach (['Account' => 'Your email is verified and your session is secure.', 'Foundation' => 'Laravel, Livewire and PostgreSQL are ready.', 'Next steps' => 'Business modules will be introduced in future phases.'] as $heading => $description)
                <article class="rounded-xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading level="2">{{ __($heading) }}</flux:heading>
                    <flux:text class="mt-3">{{ __($description) }}</flux:text>
                </article>
            @endforeach
        </section>

        <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" level="2">{{ __('Your administration workspace') }}</flux:heading>
            <flux:text class="mt-3 max-w-2xl">{{ __('Phase 1 establishes authentication and navigation. This dashboard is a placeholder for future business features.') }}</flux:text>
            <flux:button :href="route('profile.edit')" class="mt-6" wire:navigate>{{ __('Manage your account') }}</flux:button>
        </section>
    </div>
</x-layouts::app>
