@if (! $rosterLoaded)
    <div class="fi-section rounded-2xl px-6 py-16 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
        </div>
        <p class="mt-4 text-base font-semibold text-gray-950 dark:text-white">Select batch &amp; date</p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Then tap <strong>Load Students</strong> to start marking.</p>
    </div>
@elseif ($roster->isEmpty())
    <div class="fi-section rounded-2xl border border-amber-200/60 bg-amber-50/50 px-6 py-12 text-center shadow-sm dark:border-amber-500/20 dark:bg-amber-500/5">
        <p class="font-semibold text-amber-900 dark:text-amber-100">No students in this batch</p>
        <p class="mt-1 text-sm text-amber-800/80 dark:text-amber-200/80">Assign students from the batch screen first.</p>
    </div>
@else
    @include('filament.pages.partials.fast-batch-attendance-roster', [
        'roster' => $roster,
        'marks' => $marks,
    ])
@endif
