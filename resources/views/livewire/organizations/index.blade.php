<div class="mx-auto w-full max-w-4xl space-y-8">
    <header><flux:heading size="xl" level="1">{{ __('Your organizations') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Choose an active organization or create a workspace. Access is checked on every request.') }}</flux:text></header>
    <div class="space-y-3">
        @forelse ($memberships as $membership)
            <article wire:key="organization-{{ $membership->id }}" class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div><flux:heading level="2">{{ $membership->organization->name }}</flux:heading>
                    <flux:text>{{ __('Membership') }}: {{ __(ucfirst($membership->status->value)) }} · {{ __('Organization') }}: {{ __(ucfirst($membership->organization->status->value)) }}</flux:text></div>
                @if ($membership->status === \App\Enums\AccessStatus::Active && $membership->organization->status === \App\Enums\AccessStatus::Active)
                    <flux:button wire:click="choose({{ $membership->organization_id }})">{{ __('Open organization') }}</flux:button>
                @endif
            </article>
        @empty
            <flux:text>{{ __('You do not belong to an organization yet.') }}</flux:text>
        @endforelse
    </div>
    <form wire:submit="create" class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
        <flux:heading level="2">{{ __('Create organization') }}</flux:heading>
        <flux:input wire:model="name" :label="__('Organization name')" required maxlength="120" />
        <flux:button type="submit" variant="primary">{{ __('Create organization') }}</flux:button>
    </form>
</div>
