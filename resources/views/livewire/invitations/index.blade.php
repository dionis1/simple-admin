<div class="mx-auto max-w-5xl space-y-6">
    <header><flux:heading size="xl" level="1">{{ __('Invitations') }}</flux:heading><flux:text>{{ __('Invite people securely. Existing memberships must be managed separately.') }}</flux:text></header>
    @if(session('message'))<p role="status">{{ __(session('message')) }}</p>@endif
    <form wire:submit="issue" class="grid gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700 sm:grid-cols-2">
        <flux:input wire:model="email" type="email" :label="__('Invitation email')" required />
        <flux:select wire:model="roleId" :label="__('Role')" required><option value="">{{ __('Choose role') }}</option>
            @foreach($roles as $role) @can('create',[\App\Models\RoleAssignment::class,$role])<option value="{{ $role->id }}">{{ __(ucfirst($role->name->value)) }}</option>@endcan @endforeach
        </flux:select>
        <flux:button type="submit" variant="primary">{{ __('Send invitation') }}</flux:button>
    </form>
    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search email')" />
        <flux:select wire:model.live="state" :label="__('Invitation status')">@foreach(['pending','expired','accepted','revoked','all'] as $value)<option value="{{ $value }}">{{ __(ucfirst($value)) }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="sort" :label="__('Sort')"><option value="newest">{{ __('Newest first') }}</option><option value="oldest">{{ __('Oldest first') }}</option></flux:select>
    </div>
    @forelse($invitations as $invitation)
        <article wire:key="invite-{{ $invitation->id }}" class="flex flex-wrap justify-between gap-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <div class="min-w-0"><flux:heading class="break-all">{{ $invitation->email }}</flux:heading><flux:text>{{ __('Expires') }}: {{ $invitation->expires_at->format('Y-m-d H:i') }}</flux:text></div>
            @if(!$invitation->accepted_at && !$invitation->revoked_at) @can('delete',$invitation)<flux:button variant="danger" wire:click="revoke({{ $invitation->id }})" wire:confirm="{{ __('Revoke this invitation?') }}">{{ __('Revoke') }}</flux:button>@endcan @endif
        </article>
    @empty <flux:text>{{ __('No invitations found.') }}</flux:text> @endforelse
    {{ $invitations->links() }}
</div>
