<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        @if (session()->has(\App\Actions\Governance\SupportAccess::SESSION_KEY))
            <div role="alert" class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border-2 border-amber-600 bg-amber-50 p-4 text-zinc-950">
                <p>{{ __('Read-only support access is active.') }} {{ __('Viewing as user ID') }} #{{ request()->attributes->get('support_subject_id') }}. {{ __('All changes and security settings are blocked.') }}</p>
                <form method="POST" action="{{ route('support.stop') }}">@csrf<flux:button type="submit">{{ __('End support access') }}</flux:button></form>
            </div>
        @endif
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
