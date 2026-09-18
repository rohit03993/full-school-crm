@php
    use App\Support\CrmMenuLabels;

    $createTeacherExamUrl = $createTeacherExamUrl ?? null;
    $uploadExcelUrl = $uploadExcelUrl ?? null;
    $teacherExamsListUrl = $teacherExamsListUrl ?? null;
    $entryMeta = $entryMeta ?? [];

    $pathBadgeClass = function (string $color): string {
        return match ($color) {
            'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
            'warning' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
        };
    };
@endphp
<div class="space-y-5">
    <div class="overflow-hidden rounded-2xl border border-primary-500/20 bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">How to add marks</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Both ways keep the same exam list. Nothing already entered is removed.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @if (filled($createTeacherExamUrl))
                    <a href="{{ $createTeacherExamUrl }}" class="rounded-xl bg-white px-4 py-4 ring-1 ring-gray-200 transition hover:ring-primary-400 dark:bg-gray-950 dark:ring-white/10">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Way 1</p>
                        <p class="mt-1 text-sm font-bold text-gray-950 dark:text-white">{{ CrmMenuLabels::createExam() }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Create the exam for a class. Teachers type marks per subject. Then approve and publish.
                        </p>
                    </a>
                @endif
                @if (filled($uploadExcelUrl))
                    <a href="{{ $uploadExcelUrl }}" class="rounded-xl bg-white px-4 py-4 ring-1 ring-gray-200 transition hover:ring-primary-400 dark:bg-gray-950 dark:ring-white/10">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Way 2</p>
                        <p class="mt-1 text-sm font-bold text-gray-950 dark:text-white">{{ CrmMenuLabels::uploadMarksExcel() }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            You already have a spreadsheet. Name the exam, upload, then View sheet and publish.
                        </p>
                    </a>
                @endif
            </div>
            @if (filled($teacherExamsListUrl))
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    <a href="{{ $teacherExamsListUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ CrmMenuLabels::teacherExamsInProgress() }}</a>
                    — drafts and subjects still waiting for marks.
                </p>
            @endif
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                After marks are in: <strong>View sheet</strong> → publish for parents. Daily roll call stays under <strong>Attendance</strong>.
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">All exams</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Each row is one exam (all subjects). Use <strong>View sheet</strong> to review and publish.
            </p>
        </div>

        <div class="grid gap-4 border-b border-gray-100 p-4 dark:border-white/10 sm:grid-cols-2 sm:p-6">
            <x-crm.select-input label="Filter by batch" for="batch-filter" wire:model.live="batchFilter">
                <option value="">All batches</option>
                @foreach ($batchOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-crm.select-input>

            <x-crm.select-input label="Filter by exam type" for="type-filter" wire:model.live="activityTypeFilter">
                <option value="">All types</option>
                @foreach ($activityTypeOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-crm.select-input>
        </div>
    </div>

    @if ($exams->total() === 0)
        <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">No exams yet</p>
            <p class="mt-2">Use <strong>Way 1</strong> or <strong>Way 2</strong> above. Existing marks are never deleted by opening this page.</p>
        </div>
    @else
        <div class="space-y-2 md:hidden">
            @foreach ($exams as $row)
                @php
                    $groupKey = (string) ($row['group_key'] ?? '');
                    $status = $declarationStatuses[$groupKey] ?? ['label' => '—', 'color' => 'gray'];
                    $path = $entryMeta[$groupKey] ?? null;
                    $badgeClass = $pathBadgeClass($status['color'] ?? 'gray');
                    $pathClass = $pathBadgeClass($path['color'] ?? 'gray');
                    $subjectNames = $row['subject_names'] ?? array_keys(array_filter(
                        $row['subjects'] ?? [],
                        fn (array $cell): bool => (int) ($cell['session_id'] ?? 0) > 0 || (int) ($cell['marks_count'] ?? 0) > 0,
                    ));
                    $studentCount = (int) ($row['student_count'] ?? 0);
                @endphp
                <div class="rounded-xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['type'] ?? '—' }} · {{ $row['batch'] ?? '—' }} · {{ $row['date']?->format('d M Y') ?? '—' }}
                            </p>
                        </div>
                        @if (($row['tracks_marks'] ?? true) && filled($groupKey))
                            <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ $status['label'] ?? '—' }}
                            </span>
                        @endif
                    </div>
                    @if (is_array($path))
                        <p class="mt-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $pathClass }}">{{ $path['label'] }}</span>
                        </p>
                    @endif
                    @if ($subjectNames !== [])
                        <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                            <p class="text-[10px] font-semibold uppercase text-gray-500">This paper</p>
                            <p class="mt-1 text-xs text-gray-700 dark:text-gray-300">{{ implode(' · ', $subjectNames) }}</p>
                            <p class="mt-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                @if ($studentCount > 0)
                                    {{ $studentCount }} {{ $studentCount === 1 ? 'student' : 'students' }}
                                @else
                                    No marks yet
                                @endif
                            </p>
                        </div>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                        @if ($row['tracks_marks'] ?? true)
                            <a href="{{ $reviewPageBaseUrl }}?group={{ urlencode($groupKey) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                                View sheet
                            </a>
                            @if (filled($path['url'] ?? null))
                                <a href="{{ $path['url'] }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                                    Enter marks
                                </a>
                            @endif
                            <a href="{{ \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest($row['label'], $row['activity_type_id'] ?? null, $row['batch_id'] ?? null, $row['date']?->format('Y-m-d')) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500">
                                {{ CrmMenuLabels::uploadMarksExcel() }}
                            </a>
                            @if (($canDeleteExams ?? false) && ($deleteEligibility[$groupKey]['allowed'] ?? false))
                                <button
                                    type="button"
                                    wire:click="deleteExam({{ \Illuminate\Support\Js::from($groupKey) }})"
                                    wire:confirm="Delete this exam and its marks? Parents have not been messaged. This cannot be undone."
                                    class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300"
                                >
                                    Delete
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden overflow-x-auto rounded-xl ring-1 ring-gray-200 md:block dark:ring-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Exam</th>
                        <th class="px-4 py-2.5">Exam type</th>
                        <th class="px-4 py-2.5">Batch</th>
                        <th class="px-4 py-2.5">Date</th>
                        <th class="px-4 py-2.5">Marks added</th>
                        <th class="px-4 py-2.5">Result</th>
                        <th class="px-4 py-2.5">This paper</th>
                        <th class="px-4 py-2.5">Students</th>
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($exams as $row)
                        @php
                            $groupKey = (string) ($row['group_key'] ?? '');
                            $status = $declarationStatuses[$groupKey] ?? ['label' => '—', 'color' => 'gray'];
                            $path = $entryMeta[$groupKey] ?? null;
                            $badgeClass = $pathBadgeClass($status['color'] ?? 'gray');
                            $pathClass = $pathBadgeClass($path['color'] ?? 'gray');
                            $subjectNames = $row['subject_names'] ?? array_keys($row['subjects'] ?? []);
                            $studentCount = (int) ($row['student_count'] ?? 0);
                        @endphp
                        <tr class="bg-white dark:bg-gray-900">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-medium text-gray-950 dark:bg-gray-900 dark:text-white">
                                {{ $row['label'] }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['type'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['batch'] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-400">
                                {{ $row['date']?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                @if (is_array($path))
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $pathClass }}">
                                        {{ $path['label'] }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if (($row['tracks_marks'] ?? true) && filled($groupKey))
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                        {{ $status['label'] ?? '—' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-700 dark:text-gray-300">
                                {{ $subjectNames !== [] ? implode(' · ', $subjectNames) : '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-xs">
                                @if ($studentCount > 0)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        {{ $studentCount }} {{ $studentCount === 1 ? 'student' : 'students' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">No marks yet</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($row['tracks_marks'] ?? true)
                                        <a
                                            href="{{ $reviewPageBaseUrl }}?group={{ urlencode($groupKey) }}"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                                        >
                                            View sheet
                                        </a>
                                        @if (filled($path['url'] ?? null))
                                            <a
                                                href="{{ $path['url'] }}"
                                                class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                                            >
                                                Enter marks
                                            </a>
                                        @endif
                                        <a
                                            href="{{ \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest(
                                                $row['label'],
                                                $row['activity_type_id'] ?? null,
                                                $row['batch_id'] ?? null,
                                                $row['date']?->format('Y-m-d'),
                                            ) }}"
                                            class="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                        >
                                            {{ CrmMenuLabels::uploadMarksExcel() }}
                                        </a>
                                        @if (($canDeleteExams ?? false) && ($deleteEligibility[$groupKey]['allowed'] ?? false))
                                            <button
                                                type="button"
                                                wire:click="deleteExam({{ \Illuminate\Support\Js::from($groupKey) }})"
                                                wire:confirm="Delete this exam and its marks? Parents have not been messaged. This cannot be undone."
                                                class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($exams->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Showing {{ $exams->firstItem() }}–{{ $exams->lastItem() }} of {{ $exams->total() }} exams
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="gotoExamPage({{ $exams->currentPage() - 1 }})"
                        @disabled($exams->onFirstPage())
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-300"
                    >
                        Previous
                    </button>
                    <span class="text-xs text-gray-500">Page {{ $exams->currentPage() }} of {{ $exams->lastPage() }}</span>
                    <button
                        type="button"
                        wire:click="gotoExamPage({{ $exams->currentPage() + 1 }})"
                        @disabled(! $exams->hasMorePages())
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-white/10 dark:text-gray-300"
                    >
                        Next
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>
