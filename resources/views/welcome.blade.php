<x-layouts::auth :title="__('Welcome')">
    <div class="space-y-6 text-center">
        <flux:heading size="xl" level="1">{{ __('Simple Admin') }}</flux:heading>
        <flux:text>{{ __('A focused workspace for your administration tools.') }}</flux:text>
        @auth
            <flux:button variant="primary" :href="route('organizations.index')" class="w-full" wire:navigate>{{ __('Choose organization') }}</flux:button>
        @else
            <flux:button variant="primary" :href="route('login')" class="w-full" wire:navigate>{{ __('Sign in') }}</flux:button>
            <flux:link :href="route('register')" wire:navigate>{{ __('Create an account') }}</flux:link>
        @endauth
    </div>
</x-layouts::auth>
