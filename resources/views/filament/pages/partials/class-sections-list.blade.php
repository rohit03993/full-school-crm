@php
    use App\Enums\BatchStaffRole;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-5xl lg:pb-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="grid flex-1 grid-cols-3 gap-3">
            @foreach ([
                ['label' => 'Sections', 'value' => $stats['sections'] ?? 0],
                ['label' => 'Programmes', 'value' => $stats['programmes'] ?? 0],
                ['label' => 'Students', 'value' => $stats['students'] ?? 0],
            ] as $stat)
                <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>
        <a
            href="{{ $addUrl }}"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500"
        >
            <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
            Add class & section
        </a>
    </div>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search class, section, code…"
                class="fi-crm-input block w-full"
            />
            <select wire:model.live="sessionFilter" class="fi-crm-input block w-full">
                <option value="">All sessions</option>
                @foreach ($sessionOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($sections->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">No classes or sections yet</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Add your first {{ strtolower($courseLabel) }} and {{ strtolower($batchLabel) }} — e.g. Class 12 + Section A.
            </p>
            <a href="{{ $addUrl }}" class="mt-4 inline-flex items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                Add class & section
            </a>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($sections as $batch)
                @php
                    $course = $batch->course;
                    $lead = $batch->staffAssignments->first(fn ($row) => $row->role === BatchStaffRole::LeadTeacher);
                    $subjectCount = $course?->subjects?->count() ?? 0;
                @endphp
                <div class="rounded-xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.03] sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-bold text-gray-950 dark:text-white">
                                {{ $displayLabel($batch) }}
                            </p>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                @if ($course)
                                    <span class="font-mono text-xs font-semibold text-gray-500">{{ $course->code }}</span>
                                    <span class="text-gray-300 dark:text-gray-600"> · </span>
                                    Fee ₹{{ number_format((float) $course->fee, 0) }}
                                @endif
                                @if ($batch->academicSession)
                                    <span class="text-gray-300 dark:text-gray-600"> · </span>
                                    {{ $batch->academicSession->name }}
                                @endif
                                <span class="text-gray-300 dark:text-gray-600"> · </span>
                                {{ $batch->active_students_count }} student{{ $batch->active_students_count === 1 ? '' : 's' }}
                                @if ($subjectCount > 0)
                                    <span class="text-gray-300 dark:text-gray-600"> · </span>
                                    {{ $subjectCount }} subject{{ $subjectCount === 1 ? '' : 's' }}
                                @endif
                            </p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                @if ($batch->trainer)
                                    <span>Faculty: <strong class="text-gray-700 dark:text-gray-300">{{ $batch->trainer->name }}</strong></span>
                                @endif
                                @if ($lead?->user)
                                    <span>Lead: <strong class="text-gray-700 dark:text-gray-300">{{ $lead->user->name }}</strong></span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ $batchEditUrl($batch->id) }}"
                                class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                            >
                                Edit section
                            </a>
                            @if ($course)
                                <a
                                    href="{{ $courseEditUrl($course->id) }}"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15"
                                >
                                    Programme & fee
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($sections->hasPages())
            <div class="pt-2">
                {{ $sections->links() }}
            </div>
        @endif
    @endif
</div>
