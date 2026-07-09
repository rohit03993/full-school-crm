@php
    use App\Enums\BatchStaffRole;
    use App\Support\ClassSectionLabel;

    $groupedSections = $sections->groupBy(fn ($batch) => $batch->course_id ?? 0);
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

    <div class="rounded-xl border border-sky-200/80 bg-sky-50/60 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-100">
        <p class="font-semibold">Subjects live on the class (programme), not on each section.</p>
        <p class="mt-1 text-sky-800 dark:text-sky-200">
            Click <strong>Subjects</strong> on a class to add English, Maths, etc. Then use <strong>Edit section</strong> to assign subject teachers.
        </p>
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
        <div class="space-y-4">
            @foreach ($groupedSections as $courseId => $batches)
                @php
                    $course = $batches->first()?->course;
                    $subjectCount = $course?->subjects?->where('is_active', true)->count() ?? 0;
                    $subjectNames = $course?->subjects?->where('is_active', true)->pluck('name')->take(5)->implode(', ') ?? '';
                @endphp
                <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.03]">
                    @if ($course)
                        <div class="flex flex-col gap-3 border-b border-gray-100 bg-gray-50/80 px-4 py-4 dark:border-white/10 dark:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Class / programme</p>
                                <p class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white">{{ $course->name }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Fee ₹{{ number_format((float) $course->fee, 0) }}
                                    <span class="text-gray-300 dark:text-gray-600"> · </span>
                                    @if ($subjectCount > 0)
                                        <span class="font-semibold text-emerald-700 dark:text-emerald-300">{{ $subjectCount }} subject{{ $subjectCount === 1 ? '' : 's' }}</span>
                                        @if ($subjectNames !== '')
                                            <span class="text-gray-400">({{ $subjectNames }}{{ $subjectCount > 5 ? '…' : '' }})</span>
                                        @endif
                                    @else
                                        <span class="font-semibold text-amber-700 dark:text-amber-300">No subjects yet — add before exams</span>
                                    @endif
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <a
                                    href="{{ $courseSubjectsUrl($course->id) }}"
                                    @class([
                                        'inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold uppercase tracking-wide',
                                        'bg-amber-500 text-white hover:bg-amber-600' => $subjectCount === 0,
                                        'bg-primary-600 text-white hover:bg-primary-500' => $subjectCount > 0,
                                    ])
                                >
                                    <x-filament::icon icon="heroicon-m-book-open" class="h-4 w-4" />
                                    Subjects
                                </a>
                                <a
                                    href="{{ $courseEditUrl($course->id) }}"
                                    class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/15"
                                >
                                    Fee & details
                                </a>
                                <button
                                    type="button"
                                    wire:click="deleteProgramme({{ $course->id }})"
                                    wire:confirm="Delete this class and all its sections? It will be removed from the website and admin lists when possible."
                                    class="inline-flex items-center rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 text-xs font-semibold text-danger-700 hover:bg-danger-100 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300 dark:hover:bg-danger-500/20"
                                >
                                    Delete class
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($batches as $batch)
                            @php
                                $lead = $batch->staffAssignments->first(fn ($row) => $row->role === BatchStaffRole::LeadTeacher);
                            @endphp
                            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Section</p>
                                    <p class="font-semibold text-gray-950 dark:text-white">
                                        {{ ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: true) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($batch->academicSession)
                                            {{ $batch->academicSession->name }}
                                            <span class="text-gray-300 dark:text-gray-600"> · </span>
                                        @endif
                                        {{ $batch->active_students_count }} student{{ $batch->active_students_count === 1 ? '' : 's' }}
                                        @if ($lead?->user)
                                            <span class="text-gray-300 dark:text-gray-600"> · </span>
                                            Lead: {{ $lead->user->name }}
                                        @endif
                                    </p>
                                </div>
                                <a
                                    href="{{ $batchEditUrl($batch->id) }}"
                                    class="inline-flex shrink-0 items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                >
                                    Edit section
                                </a>
                            </div>
                        @endforeach
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
