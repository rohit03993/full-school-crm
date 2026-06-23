@php
    use Illuminate\Support\Collection;

    $selected = $activityTypes->firstWhere('slug', $selectedSlug) ?? $activityTypes->first();
    $selectedId = $selected?->id;
    $isLoaded = $selectedId ? ($loaded[$selectedId] ?? false) : false;
    $selectedRecords = $selectedId ? ($records[$selectedId] ?? new Collection) : new Collection;
@endphp

<div class="space-y-4">
    <div class="rounded-2xl bg-gray-50/80 p-2 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
        <p class="px-2 pb-2 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            Workshops, tests & events
        </p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($activityTypes as $type)
                <button
                    type="button"
                    wire:click="selectActivitySubTab('{{ $type->slug }}')"
                    wire:key="activity-subtab-{{ $type->slug }}"
                    @class([
                        'rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition ring-1',
                        'bg-primary-600 text-white ring-primary-600 shadow-sm' => $selected?->id === $type->id,
                        'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10' => $selected?->id !== $type->id,
                    ])
                >
                    {{ $type->name }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($selected)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3.5 sm:px-6 dark:border-white/10">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">{{ $selected->name }}</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    @if ($selected->supportsScoring())
                        Marks and test history for this student
                    @else
                        Attendance records for this activity type
                    @endif
                </p>
            </div>
            <div class="p-4 sm:p-6">
                @include('filament.pages.partials.student-profile-activities', [
                    'activityType' => $selected,
                    'loaded' => $isLoaded,
                    'records' => $selectedRecords,
                ])
            </div>
        </div>
    @endif
</div>
