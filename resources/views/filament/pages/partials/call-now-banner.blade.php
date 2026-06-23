@php
    $tel = filled($mobile ?? null) && strlen(preg_replace('/\D/', '', $mobile)) >= 10
        ? 'tel:+91'.substr(preg_replace('/\D/', '', $mobile), -10)
        : null;
@endphp

@if ($tel)
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">Step 1 — Call on your phone</p>
        <a
            href="{{ $tel }}"
            class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3.5 text-base font-bold text-white hover:bg-emerald-500 sm:w-auto"
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
            </svg>
            Call Now
        </a>
        <p class="mt-2 text-xs text-emerald-900 dark:text-emerald-200">Then complete Step 2 below and save.</p>
    </div>
@endif
