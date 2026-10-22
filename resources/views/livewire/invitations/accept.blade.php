<div class="mx-auto max-w-xl space-y-6">
    <flux:heading size="xl" level="1">{{ __('Review invitation') }}</flux:heading>
    <flux:text>{{ __('You have been invited to :organization.', ['organization'=>$organization->name]) }}</flux:text>
    <flux:text>{{ __('Role') }}: {{ __(ucfirst($role->name->value)) }}</flux:text>
    @error('invitation')<p role="alert">{{ $message }}</p>@enderror
    <flux:button variant="primary" wire:click="accept">{{ __('Accept invitation') }}</flux:button>
</div>
