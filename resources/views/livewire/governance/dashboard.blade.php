<div class="mx-auto w-full max-w-6xl space-y-8">
    <header><flux:heading size="xl" level="1">{{ __('Workspace overview') }}</flux:heading><flux:text class="mt-2">{{ __('Activity and security for the active organization.') }}</flux:text></header>
    <section class="grid gap-4 md:grid-cols-3" aria-label="{{ __('Organization activity') }}">
        @foreach (['Pending invitations' => $invitations, 'Pending approvals' => $approvals, 'Security events (24 hours)' => $securityEvents] as $label => $count)
            @if ($count !== null)<article class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:heading level="2">{{ __($label) }}</flux:heading><p class="mt-3 text-3xl font-semibold">{{ $count }}</p></article>@endif
        @endforeach
    </section>
    <section class="space-y-4"><flux:heading level="2">{{ __('Recent activity') }}</flux:heading>
        @forelse ($activity as $event)<div class="flex flex-wrap justify-between gap-2 rounded border border-zinc-200 p-3 dark:border-zinc-700"><span>{{ $event->event }} · {{ $event->target_type }} #{{ $event->target_id }}</span><time>{{ $event->occurred_at->toIso8601String() }}</time></div>
        @empty<flux:text>{{ __('No visible activity. Additional details require audit permission.') }}</flux:text>@endforelse
    </section>
    @if ($health !== null)
        <section class="space-y-3 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700"><flux:heading level="2">{{ __('Service health') }}</flux:heading>
            @foreach ($health as $service => $healthy)<p>{{ __($service) }}: {{ $healthy ? __('Healthy') : __('Unavailable or stale') }}</p>@endforeach
            <flux:heading level="3">{{ __('Recent queue failures for this organization') }}</flux:heading>
            @forelse ($failures as $failure)<p>{{ $failure->failed_at }} · {{ $failure->exception_class }} · {{ $failure->job_uuid }}</p>@empty<flux:text>{{ __('No recorded queue failures.') }}</flux:text>@endforelse
        </section>
    @endif
</div>
