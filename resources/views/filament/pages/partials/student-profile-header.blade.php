@php
    use App\Enums\ProfilePhase;

    $status = $record->status;
    $phase = $profile['phase'];
@endphp

@if (($phase === ProfilePhase::Enrolled || $phase === ProfilePhase::ActiveStudent) && ($profile['dossier'] ?? null))
    <div class="fi-student-profile-shell">
        @include('filament.pages.partials.student-profile-header-dossier', [
            'record' => $record,
            'profile' => $profile,
        ])
    </div>
@else
    @php
        $items = $profile['items'];
        $leadSources = $profile['lead_sources'];
        $columnCount = min(count($items), 6);
        $highlightLabels = collect(['Website', 'Walk-in'])
            ->merge(collect(\App\Support\MeetingForOptions::active())->pluck('label'))
            ->all();
    @endphp

    <div class="fi-student-profile-shell overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-4 py-4 sm:px-6 sm:py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-bold text-gray-950 sm:text-xl dark:text-white">{{ $record->name }}</h2>
                    <div class="mt-1 flex items-center gap-2">
                        <p class="text-base font-semibold text-primary-600 dark:text-primary-400">{{ $record->mobile }}</p>
                        @include('filament.pages.partials.student-call-button', ['record' => $record])
                    </div>
                    @if ($record->activeEnrollment)
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $record->activeEnrollment->enrollment_number }}</span>
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                            {{ $status->label() }}
                        </span>
                    </div>

                    @if (($leadSources['website_count'] ?? 0) > 0 || ($leadSources['walk_in_count'] ?? 0) > 0)
                        <div class="mt-3">
                            @include('filament.pages.partials.lead-source-badges', ['leadSources' => $leadSources])
                        </div>
                    @endif

                    @include('filament.pages.partials.lead-intent-highlight', ['leadSources' => $leadSources])

                    @if (! empty(array_filter($leadSources['meeting_for_counts'] ?? [])))
                        <div class="mt-3">
                            @include('filament.pages.partials.meeting-for-badges', ['leadSources' => $leadSources])
                        </div>
                    @endif

                    @if (($leadSources['website_count'] ?? 0) > 0 || ($leadSources['walk_in_count'] ?? 0) > 0)
                        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $leadSources['headline'] }}
                        </p>
                        @if (filled($leadSources['detail'] ?? null))
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $leadSources['detail'] }}
                            </p>
                        @endif
                    @endif

                    @if ($phase->isLeadStage())
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Lead profile — track website vs walk-in enquiries below
                        </p>
                    @elseif ($phase === ProfilePhase::Admission)
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Admission in progress — complete form in the Admission tab
                        </p>
                    @endif

                    @include('filament.pages.partials.student-calling-assignment-banner', [
                        'callingAssignment' => $profile['calling_assignment'] ?? null,
                    ])

                    @include('filament.pages.partials.student-last-call-summary', ['record' => $record])
                </div>
            </div>
        </div>

        <div @class([
            'grid gap-2 border-t border-gray-100 px-4 py-3 sm:gap-3 sm:px-6 sm:py-4 dark:border-white/10',
            'grid-cols-2' => $columnCount <= 2,
            'grid-cols-2 sm:grid-cols-3' => $columnCount === 3,
            'grid-cols-2 sm:grid-cols-4' => $columnCount === 4,
            'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6' => $columnCount >= 5,
        ])>
            @foreach ($items as $counter)
                <div @class([
                    'rounded-xl px-3 py-2.5',
                    'bg-emerald-500/10 ring-1 ring-emerald-500/15 dark:bg-emerald-500/5' => $counter['label'] === 'Website',
                    'bg-sky-500/10 ring-1 ring-sky-500/15 dark:bg-sky-500/5' => $counter['label'] === 'Walk-in',
                    'bg-gray-50 dark:bg-white/5' => ! in_array($counter['label'], $highlightLabels, true),
                ])>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs dark:text-gray-400">{{ $counter['label'] }}</p>
                    <p @class([
                        'mt-0.5 truncate text-base font-bold sm:text-lg',
                        'text-emerald-700 dark:text-emerald-400' => $counter['label'] === 'Website',
                        'text-sky-800 dark:text-sky-400' => $counter['label'] === 'Walk-in',
                        'text-gray-950 dark:text-white' => ! in_array($counter['label'], $highlightLabels, true),
                    ])>{{ $counter['value'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif
