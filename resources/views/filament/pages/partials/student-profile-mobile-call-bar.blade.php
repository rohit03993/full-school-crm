@if ($record->isCallable())
    @php
        $telUrl = $record->telUrl();
        $notConnectedAttempts = (int) ($record->not_connected_attempts_count ?? 0);
    @endphp

    <div class="fi-student-profile-mobile-call lg:hidden">
        <div class="fi-student-profile-mobile-call__spacer h-[4rem]" aria-hidden="true"></div>

        <div class="fi-student-profile-mobile-call__bar fixed inset-x-0 z-40 border-t border-emerald-500/20 bg-white/95 px-3 py-2.5 shadow-[0_-6px_24px_rgba(5,150,105,0.18)] backdrop-blur-md dark:border-emerald-500/25 dark:bg-gray-900/95">
            <div class="mx-auto flex max-w-lg items-stretch">
                <button
                    type="button"
                    onclick="window.CrmPendingCall.start({{ $record->id }}, @js($record->name), @js($record->mobile), @js($telUrl), {{ $notConnectedAttempts }})"
                    class="fi-student-call-bar__primary inline-flex min-h-[3rem] w-full touch-manipulation items-center justify-center gap-2.5 rounded-xl bg-emerald-600 px-4 text-base font-bold text-white shadow-md shadow-emerald-600/25 transition active:scale-[0.98] hover:bg-emerald-500"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/20">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                        </svg>
                    </span>
                    <span class="min-w-0 truncate text-left leading-tight">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-emerald-100">Call now</span>
                        <span class="block truncate font-mono text-sm">{{ $record->mobile }}</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif
