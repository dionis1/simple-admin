<div class="mx-auto w-full max-w-6xl space-y-6">
    <flux:heading size="xl" level="1">{{ __('Audit history') }}</flux:heading>
    <flux:text>{{ __('Sensitive values are redacted. Exports contain at most 1,000 matching events.') }}</flux:text>
    <div class="flex flex-wrap items-end gap-4">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search event, target, actor ID or correlation ID')" maxlength="100" />
        @can('export', \App\Models\AuditEvent::class)
            <flux:button :href="route('audit.export', ['search' => $search])">{{ __('Export CSV') }}</flux:button>
        @endcan
    </div>
    <flux:error name="search" />
    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ __('Organization audit events') }}</caption>
            <thead><tr>@foreach (['Time', 'Event', 'Actor ID', 'Target', 'Correlation ID', 'Redacted changes'] as $label)<th scope="col" class="p-3">{{ __($label) }}</th>@endforeach</tr></thead>
            <tbody>@forelse ($events as $event)
                <tr wire:key="audit-{{ $event->id }}" class="border-t border-zinc-200 dark:border-zinc-700">
                    <td class="p-3 whitespace-nowrap">{{ $event->occurred_at->toIso8601String() }}</td><td class="p-3">{{ $event->event }}</td><td class="p-3">{{ $event->actor_id ?? __('System') }}</td><td class="p-3">{{ $event->target_type }} #{{ $event->target_id }}</td><td class="p-3 break-all">{{ $event->correlation_id }}</td><td class="max-w-sm p-3 break-all">{{ json_encode($event->changes) }}</td>
                </tr>
            @empty<tr><td colspan="6" class="p-6">{{ __('No audit events found.') }}</td></tr>@endforelse</tbody>
        </table>
    </div>
    {{ $events->links() }}
</div>
