@php
    use App\Enums\ProfilePhase;

    $status = $record->status;
    $phase = $profile['phase'];
@endphp

@if (($phase === ProfilePhase::Enrolled || $phase === ProfilePhase::ActiveStudent) && ($profile['dossier'] ?? null))
    @include('filament.pages.partials.student-profile-header-dossier', [
        'record' => $record,
        'profile' => $profile,
    ])
@else
    @php
        $items = $profile['items'];
        $leadSources = $profile['lead_sources'];
        $columnCount = min(count($items), 6);
        $schoolLabel = \App\Enums\MeetingFor::School->label();
        $coachingLabel = \App\Enums\MeetingFor::Coaching->label();
        $sourceLabels = ['Website', 'Walk-in', $schoolLabel, $coachingLabel];
    @endphp

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="px-4 py-4 sm:px-6 sm:py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-lg font-bold text-gray-950 sm:text-xl dark:text-white">{{ $record->name }}</h2>
                    <p class="mt-1 text-base font-semibold text-primary-600 dark:text-primary-400">
                        {{ $record->mobile }}
                    </p>
                    @if ($record->email || $record->activeEnrollment)
                        <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                            @if ($record->email){{ $record->email }}@endif
                            @if ($record->activeEnrollment)
                                @if ($record->email) · @endif
                                <span class="font-mono">{{ $record->activeEnrollment->enrollment_number }}</span>
                            @endif
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

                    @if (($leadSources['school_count'] ?? 0) > 0 || ($leadSources['coaching_count'] ?? 0) > 0)
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
                    'bg-amber-500/10 ring-1 ring-amber-500/15 dark:bg-amber-500/5' => $counter['label'] === $schoolLabel,
                    'bg-violet-500/10 ring-1 ring-violet-500/15 dark:bg-violet-500/5' => $counter['label'] === $coachingLabel,
                    'bg-gray-50 dark:bg-white/5' => ! in_array($counter['label'], $sourceLabels, true),
                ])>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 sm:text-xs dark:text-gray-400">{{ $counter['label'] }}</p>
                    <p @class([
                        'mt-0.5 truncate text-base font-bold sm:text-lg',
                        'text-emerald-700 dark:text-emerald-400' => $counter['label'] === 'Website',
                        'text-sky-800 dark:text-sky-400' => $counter['label'] === 'Walk-in',
                        'text-amber-800 dark:text-amber-400' => $counter['label'] === $schoolLabel,
                        'text-violet-800 dark:text-violet-400' => $counter['label'] === $coachingLabel,
                        'text-gray-950 dark:text-white' => ! in_array($counter['label'], $sourceLabels, true),
                    ])>{{ $counter['value'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endif
