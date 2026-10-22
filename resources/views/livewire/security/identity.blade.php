<div class="mx-auto w-full max-w-5xl space-y-8">
    <header><flux:heading size="xl" level="1">{{ __('Account security') }}</flux:heading><flux:text>{{ __('Manage your identity, authenticators, browser sessions and organization API tokens.') }}</flux:text></header>
    @if(session('message'))<p role="status">{{ __(session('message')) }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-lg border border-red-500 p-4"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <flux:input wire:model="currentPassword" type="password" autocomplete="current-password" :label="__('Current password for security changes')" />
    <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg" level="2">{{ __('Account status') }}: {{ __(ucfirst($account->status->value)) }}</flux:heading>
        <flux:text>{{ __('Deactivation blocks organization and API access. Transfer organization and team ownership before deactivating. Reactivation does not restore suspended memberships.') }}</flux:text>
        @if($account->status === \App\Enums\AccessStatus::Active)<flux:button variant="danger" wire:click="lifecycle(false)" wire:confirm="{{ __('Deactivate your account?') }}">{{ __('Deactivate account') }}</flux:button>
        @else<flux:button variant="primary" wire:click="lifecycle(true)">{{ __('Reactivate account') }}</flux:button>@endif
    </section>
    @if($account->status === \App\Enums\AccessStatus::Active)
    <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg" level="2">{{ __('Two-factor authentication') }}</flux:heading>
        <flux:text>{{ __('Use an authenticator app and keep your recovery codes offline.') }}</flux:text>
        @if(!$account->two_factor_secret)<flux:button wire:click="mfa('enable')">{{ __('Set up authenticator') }}</flux:button>
        @elseif(!$account->two_factor_confirmed_at)
            @if($setupQr)<div class="w-fit rounded bg-white p-4" role="img" aria-label="{{ __('Authenticator setup QR code') }}">{!! $setupQr !!}</div>
                <p class="break-all font-mono text-sm">{{ decrypt($account->two_factor_secret) }}</p>
            @endif
            <flux:input wire:model="code" :label="__('Authenticator code')" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
            <flux:button wire:click="mfa('confirm')" variant="primary">{{ __('Confirm authenticator') }}</flux:button>
        @else
            <flux:text>{{ __('Two-factor authentication is enabled.') }}</flux:text>
            <flux:button wire:click="mfa('regenerate')" wire:confirm="{{ __('Replace all recovery codes?') }}">{{ __('Regenerate recovery codes') }}</flux:button>
        @endif
        @if($account->two_factor_secret)<flux:button variant="danger" wire:click="mfa('disable')" wire:confirm="{{ __('Disable two-factor authentication?') }}">{{ __('Disable authenticator') }}</flux:button>@endif
        @if($recoveryCodes)<div role="status"><flux:heading>{{ __('Save these recovery codes now') }}</flux:heading><ul class="font-mono">@foreach($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach</ul></div>@endif
    </section>
    @endif
    <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg" level="2">{{ __('Browser sessions') }}</flux:heading>
        @forelse($sessions as $browserSession)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 py-3 dark:border-zinc-700" wire:key="session-{{ $browserSession->reference }}">
                <div class="min-w-0"><p class="break-all">{{ \Illuminate\Support\Str::limit($browserSession->user_agent,120) }}</p><flux:text>{{ $browserSession->ip_address }} · {{ date('Y-m-d H:i',$browserSession->last_activity) }} @if($browserSession->current) · {{ __('Current session') }}@endif</flux:text></div>
                <flux:button variant="danger" wire:click="revokeSession('{{ $browserSession->reference }}')" wire:confirm="{{ __('Revoke this session?') }}">{{ __('Revoke') }}</flux:button>
            </div>
        @empty<flux:text>{{ __('No database sessions found.') }}</flux:text>@endforelse
    </section>
    <section class="space-y-4 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <flux:heading size="lg" level="2">{{ __('API tokens') }}</flux:heading>
        <flux:text>{{ __('Tokens belong to the active organization, expire within 90 days and cannot exceed your current permissions. Treat them like passwords.') }}</flux:text>
        @if(count($availableAbilities))
            <form wire:submit="createToken" class="space-y-4">
                <flux:input wire:model="tokenName" :label="__('Token name')" maxlength="80" required />
                <flux:input wire:model="tokenDays" type="number" :label="__('Expires in days')" min="1" max="90" required />
                <fieldset><legend class="mb-2 font-medium">{{ __('Token abilities') }}</legend><div class="grid gap-2 sm:grid-cols-2">@foreach($availableAbilities as $ability)<flux:checkbox wire:model="abilities" value="{{ $ability->value }}" :label="$ability->value" />@endforeach</div></fieldset>
                <flux:button type="submit" variant="primary">{{ __('Create API token') }}</flux:button>
            </form>
        @else<flux:text>{{ __('Select an active organization to create a token.') }}</flux:text>@endif
        @if($newToken)<div role="status" class="rounded-lg border border-amber-500 p-4"><p>{{ __('Copy this token now. It will not be shown again.') }}</p><code class="break-all">{{ $newToken }}</code></div>@endif
        @forelse($tokens as $token)<div class="flex flex-wrap justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700" wire:key="token-{{ $token->id }}"><div><p>{{ $token->name }}</p><flux:text>{{ $token->organization->name }}</flux:text><flux:text>{{ __('Expires') }}: {{ $token->expires_at?->format('Y-m-d') }}</flux:text><p class="text-sm">{{ implode(', ',$token->abilities ?? []) }}</p></div><flux:button variant="danger" wire:click="revokeToken({{ $token->id }})" wire:confirm="{{ __('Revoke this token?') }}">{{ __('Revoke') }}</flux:button></div>@empty<flux:text>{{ __('No API tokens.') }}</flux:text>@endforelse
    </section>
</div>
