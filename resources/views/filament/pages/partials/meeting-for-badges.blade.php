@php
    $schoolCount = $leadSources['school_count'] ?? 0;
    $coachingCount = $leadSources['coaching_count'] ?? 0;
@endphp

@if ($schoolCount > 0 || $coachingCount > 0)
    <div class="flex flex-wrap items-center gap-2">
        @if ($schoolCount > 0)
            @include('filament.pages.partials.meeting-for-badge', [
                'meetingFor' => \App\Enums\MeetingFor::School,
                'size' => 'md',
            ])
            @if ($schoolCount > 1)
                <span class="text-xs font-bold text-amber-700 dark:text-amber-400">×{{ $schoolCount }}</span>
            @endif
        @endif

        @if ($coachingCount > 0)
            @include('filament.pages.partials.meeting-for-badge', [
                'meetingFor' => \App\Enums\MeetingFor::Coaching,
                'size' => 'md',
            ])
            @if ($coachingCount > 1)
                <span class="text-xs font-bold text-violet-700 dark:text-violet-400">×{{ $coachingCount }}</span>
            @endif
        @endif
    </div>
@endif
