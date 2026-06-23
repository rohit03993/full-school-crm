@if (! $rosterLoaded)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Select a batch and date, then tap <span class="font-semibold">Load Students</span>.
    </div>
@elseif ($roster->isEmpty())
    <div class="rounded-xl bg-amber-50 px-4 py-8 text-center text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30">
        No active students in this batch. Assign students from the batch edit screen first.
    </div>
@else
    @include('filament.pages.partials.fast-batch-attendance-roster', [
        'roster' => $roster,
        'marks' => $marks,
    ])
@endif
