<div class="mx-auto w-full max-w-5xl space-y-6">
    <flux:heading size="xl" level="1">{{ __('Approvals') }}</flux:heading>
    <flux:text>{{ __('High-risk operations require an independent reviewer. Support access is read-only and expires 15 minutes after approval.') }}</flux:text>
    @if (session('message'))<div role="status">{{ session('message') }}</div>@endif
    @if ($errors->any())<div role="alert" class="rounded border border-red-500 p-4">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form wire:submit="requestApproval" class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading level="2">{{ __('Request approval') }}</flux:heading>
        <flux:select wire:model="operation" :label="__('Operation')">
            <option value="support_access">{{ __('Temporary support access') }}</option>
            @can('tenant.permission', \App\Enums\PermissionKey::RequestApproval)<option value="revoke_org_tokens">{{ __('Revoke all organization API tokens') }}</option>@endcan
        </flux:select>
        <flux:select wire:model="targetId" :label="__('Support target membership')">
            <option value="">{{ __('Choose a membership for support access') }}</option>
            @foreach ($targets as $target)<option value="{{ $target->id }}">#{{ $target->id }} {{ $target->user->name }}</option>@endforeach
        </flux:select>
        <flux:textarea wire:model="reason" :label="__('Reason (10–500 characters)')" maxlength="500" required />
        <flux:button type="submit" variant="primary">{{ __('Request approval') }}</flux:button>
    </form>
    <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading level="2">{{ __('Confirm sensitive actions') }}</flux:heading>
        <flux:input type="password" wire:model="currentPassword" :label="__('Current password')" autocomplete="current-password" />
        <flux:input wire:model="totp" :label="__('Current TOTP code (support access only)')" autocomplete="one-time-code" inputmode="numeric" maxlength="6" />
        @foreach ($grants as $grant)
            <div class="flex flex-wrap items-center gap-3" wire:key="grant-{{ $grant->id }}">
                <flux:text>{{ __('Approved support grant') }} #{{ $grant->id }} · {{ __('Expires') }} {{ $grant->expires_at->toIso8601String() }}</flux:text>
                <flux:button wire:click="start({{ $grant->id }})" wire:loading.attr="disabled">{{ __('Start read-only support access') }}</flux:button>
            </div>
        @endforeach
    </section>
    <flux:select wire:model.live="state" :label="__('Filter by state')">
        <option value="">{{ __('All states') }}</option>
        @foreach (['pending', 'executed', 'rejected', 'cancelled', 'expired'] as $stateOption)<option value="{{ $stateOption }}">{{ __($stateOption) }}</option>@endforeach
    </flux:select>
    @forelse ($requests as $approval)
        <article wire:key="approval-{{ $approval->id }}" class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading level="2">#{{ $approval->id }} · {{ __($approval->operation) }} · {{ __($approval->expires_at->isPast() && $approval->state === 'pending' ? 'expired' : $approval->state) }}</flux:heading>
            <p class="break-words">{{ $approval->reason }}</p>
            <flux:text>{{ __('Requester ID') }}: {{ $approval->requester_id }} · {{ __('Expires') }} {{ $approval->expires_at->toIso8601String() }}</flux:text>
            @if ($approval->state === 'pending' && $approval->expires_at->isFuture())
                <div class="flex flex-wrap gap-3">
                    @can('review', $approval)
                        <flux:button wire:click="decide({{ $approval->id }}, true)" wire:confirm="{{ __('Approve and execute this operation?') }}" wire:loading.attr="disabled">{{ __('Approve and execute') }}</flux:button>
                        <flux:button wire:click="decide({{ $approval->id }}, false)" wire:loading.attr="disabled">{{ __('Reject') }}</flux:button>
                    @endcan
                    @if ($approval->requester_id === auth()->id())<flux:button wire:click="cancel({{ $approval->id }})">{{ __('Cancel request') }}</flux:button>@endif
                </div>
            @endif
        </article>
    @empty<flux:text>{{ __('No approval requests found.') }}</flux:text>@endforelse
    {{ $requests->links() }}
</div>
