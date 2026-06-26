@php
    $count = (int) ($count ?? 0);
    $filterUrl = $filterUrl ?? '#';
@endphp

@if ($count > 0)
    <div class="mb-4 rounded-xl border border-danger-300 bg-danger-50 px-4 py-4 text-sm text-danger-950 ring-1 ring-danger-200 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-100 dark:ring-danger-500/30">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </span>
                <div>
                    <p class="font-bold text-danger-900 dark:text-danger-200">
                        {{ $count }} student{{ $count === 1 ? '' : 's' }} need a mobile number
                    </p>
                    <p class="mt-1 text-danger-800 dark:text-danger-300">
                        Imported without a valid mobile (missing, invalid, duplicate in Excel, or corrupted from Excel).
                        Each row shows the exact reason — open the profile and add the correct WhatsApp / mobile number.
                    </p>
                </div>
            </div>
            <a
                href="{{ $filterUrl }}"
                class="inline-flex shrink-0 items-center justify-center rounded-lg bg-danger-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-danger-500 dark:bg-danger-500 dark:hover:bg-danger-400"
            >
                Show only these students
            </a>
        </div>
    </div>
@endif
