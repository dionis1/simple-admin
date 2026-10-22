<div class="mx-auto w-full max-w-3xl space-y-6">
    <flux:heading size="xl" level="1">{{ $organization->name }}</flux:heading>
    <flux:text>{{ __('Organization status') }}: {{ $organization->status->value }}</flux:text>
    @if (session('message')) <flux:text role="status">{{ __(session('message')) }}</flux:text> @endif
    @can('update', $organization)
        <form wire:submit="save" class="space-y-4">
            <flux:input wire:model="name" :label="__('Organization name')" required maxlength="120" />
            <flux:button type="submit" variant="primary">{{ __('Save organization') }}</flux:button>
        </form>
    @endcan
</div>
