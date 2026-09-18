@php
    $canViewMobile = \App\Support\CrmAccess::canViewStudentMobile(auth()->user());
@endphp

@if ($canViewMobile && $record->isCallable())
    @php
        $telUrl = $record->telUrl();
        $notConnectedAttempts = (int) ($record->not_connected_attempts_count ?? 0);
    @endphp

    {{-- Slim bar above the bottom nav. Page padding is in theme.css. --}}
    <div class="fi-student-profile-mobile-call lg:hidden" aria-hidden="false">
        <div class="fi-student-profile-mobile-call__bar fixed inset-x-0 z-40 border-t border-emerald-500/20 bg-white/95 px-2 py-1.5 shadow-[0_-4px_16px_rgba(5,150,105,0.12)] backdrop-blur-md dark:border-emerald-500/25 dark:bg-gray-900/95">
            <button
                type="button"
                onclick="window.CrmPendingCall.start({{ $record->id }}, @js($record->name), @js($record->mobile), @js($telUrl), {{ $notConnectedAttempts }})"
                class="fi-student-call-bar__primary inline-flex min-h-10 w-full touch-manipulation items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 text-sm font-semibold text-white shadow-sm transition active:scale-[0.99] hover:bg-emerald-500"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
                <span class="truncate">Call {{ $record->mobile }}</span>
            </button>
        </div>
    </div>
@endif
