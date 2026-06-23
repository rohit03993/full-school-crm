@if (! $loaded)
    <div wire:init="loadActivityTab({{ $activityType->id }})" class="flex items-center justify-center py-12 text-sm text-gray-500 dark:text-gray-400">
        Loading…
    </div>
@elseif ($records->isEmpty())
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        @if ($activityType->supportsScoring())
            No {{ strtolower($activityType->plural_name) }} with marks recorded yet.
        @else
            No {{ strtolower($activityType->plural_name) }} attendance recorded yet. Mark under <strong>Workshops &amp; Events</strong>.
        @endif
    </div>
@elseif ($activityType->supportsScoring())
    @include('filament.pages.partials.student-profile-exam-marks-matrix', [
        'matrix' => \App\Support\StudentExamMarksMatrix::fromRecords($records),
    ])
@else
    @include('filament.pages.partials.student-profile-activity-table', [
        'records' => $records,
        'attendanceOnly' => true,
    ])
@endif
