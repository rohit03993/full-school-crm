@if ($record->isCallable())
    @php
        $telUrl = $record->telUrl();
        $notConnectedAttempts = (int) ($record->not_connected_attempts_count ?? 0);
    @endphp
    <div class="flex items-center gap-1.5">
        <button
            type="button"
            onclick="window.CrmPendingCall.start({{ $record->id }}, @js($record->name), @js($record->mobile), @js($telUrl), {{ $notConnectedAttempts }})"
            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-sm ring-2 ring-white hover:bg-emerald-500 dark:ring-gray-900"
            title="Call {{ $record->mobile }}"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
            </svg>
        </button>
        <button
            type="button"
            wire:click="openLogCallModal"
            class="hidden text-[10px] font-semibold uppercase tracking-wide text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 sm:inline"
            title="Log call result without opening dialer"
        >
            Log
        </button>
    </div>
@endif
