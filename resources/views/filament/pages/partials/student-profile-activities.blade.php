@if (! $loaded)
    <div wire:init="loadActivityTab({{ $activityType->id }})" class="flex items-center justify-center py-12 text-sm text-gray-500 dark:text-gray-400">
        Loading…
    </div>
@elseif ($records->isEmpty())
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        No {{ strtolower($activityType->plural_name) }} marked present yet.
    </div>
@else
    @include('filament.pages.partials.student-profile-activity-table', ['records' => $records])
@endif
