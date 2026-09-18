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
<div class="space-y-3">
    @if (filled($renameGroupKey ?? null))
        <div class="rounded-2xl border border-primary-200 bg-white p-4 shadow-sm dark:border-primary-500/30 dark:bg-gray-900">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Rename exam</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Marks stay the same. Only the title on this list and on View sheet changes.</p>
            <input type="text" wire:model="renameExamName" class="fi-crm-input mt-3 block w-full" maxlength="255">
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" wire:click="saveRename" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">Save name</button>
                <button type="button" wire:click="cancelRename" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">Cancel</button>
            </div>
        </div>
    @endif

    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex justify-end border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
            <div class="grid w-full grid-cols-2 gap-2 sm:max-w-md">
                <x-crm.select-input label="Batch" for="batch-filter" wire:model.live="batchFilter">
                    <option value="">All batches</option>
                    @foreach ($batchOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select-input>
                <x-crm.select-input label="Type" for="type-filter" wire:model.live="activityTypeFilter">
                    <option value="">All types</option>
                    @foreach ($activityTypeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select-input>
            </div>
        </div>

        @if ($exams->total() === 0)
            <div class="px-4 py-10 text-center text-sm text-gray-600 dark:text-gray-400">
                <p class="font-semibold text-gray-950 dark:text-white">No exams yet</p>
                <p class="mt-1">Use Teachers enter marks or Upload Excel. Existing marks are never deleted by opening this page.</p>
            </div>
        @else
            <div class="space-y-2 p-3 md:hidden">
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
                        $excelUrl = \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest(
                            $row['label'],
                            $row['activity_type_id'] ?? null,
                            $row['batch_id'] ?? null,
                            $row['date']?->format('Y-m-d'),
                            $groupKey,
                        );
                    @endphp
                    <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $row['batch'] ?? '—' }} · {{ $row['date']?->format('d M Y') ?? '—' }}
                                </p>
                            </div>
                            @if ($studentCount > 0)
                                <span class="shrink-0 text-xs font-medium text-emerald-700 dark:text-emerald-300">{{ $studentCount }}</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @if (is_array($path))
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $pathClass }}">{{ $path['label'] }}</span>
                            @endif
                            @if (($row['tracks_marks'] ?? true) && filled($groupKey))
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badgeClass }}">{{ $status['label'] ?? '—' }}</span>
                            @endif
                        </div>
                        @if ($subjectNames !== [])
                            <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">{{ implode(' · ', $subjectNames) }}</p>
                        @endif
                        @if ($row['tracks_marks'] ?? true)
                            <div class="mt-3">
                                @include('filament.resources.activity-sessions.partials.exam-row-actions', [
                                    'reviewUrl' => $reviewPageBaseUrl.'?group='.urlencode($groupKey),
                                    'pathUrl' => is_array($path) ? ($path['url'] ?? null) : null,
                                    'excelUrl' => $excelUrl,
                                    'excelLabel' => $studentCount > 0 ? 'Update Excel' : CrmMenuLabels::uploadMarksExcel(),
                                    'canRename' => $canRenameExams ?? false,
                                    'examLabel' => (string) ($row['label'] ?? ''),
                                    'canDelete' => ($canDeleteExams ?? false) && ($deleteEligibility[$groupKey]['allowed'] ?? false),
                                    'groupKey' => $groupKey,
                                    'stacked' => true,
                                ])
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto md:block">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2 dark:bg-gray-900">Exam</th>
                            <th class="px-4 py-2">Paper</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Students</th>
                            <th class="px-4 py-2 text-right"> </th>
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
                                $excelUrl = \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest(
                                    $row['label'],
                                    $row['activity_type_id'] ?? null,
                                    $row['batch_id'] ?? null,
                                    $row['date']?->format('Y-m-d'),
                                    $groupKey,
                                );
                            @endphp
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="sticky left-0 z-10 bg-white px-4 py-2.5 dark:bg-gray-900">
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['type'] ?? '—' }} · {{ $row['batch'] ?? '—' }} · {{ $row['date']?->format('d M Y') ?? '—' }}
                                    </p>
                                </td>
                                <td class="max-w-[14rem] px-4 py-2.5 text-xs text-gray-600 dark:text-gray-400">
                                    {{ $subjectNames !== [] ? implode(' · ', $subjectNames) : '—' }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex flex-col items-start gap-1">
                                        @if (is_array($path))
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $pathClass }}">{{ $path['label'] }}</span>
                                        @endif
                                        @if (($row['tracks_marks'] ?? true) && filled($groupKey))
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium {{ $badgeClass }}">{{ $status['label'] ?? '—' }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-xs">
                                    @if ($studentCount > 0)
                                        <span class="font-medium text-emerald-700 dark:text-emerald-300">{{ $studentCount }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @if ($row['tracks_marks'] ?? true)
                                        @include('filament.resources.activity-sessions.partials.exam-row-actions', [
                                            'reviewUrl' => $reviewPageBaseUrl.'?group='.urlencode($groupKey),
                                            'pathUrl' => is_array($path) ? ($path['url'] ?? null) : null,
                                            'excelUrl' => $excelUrl,
                                            'excelLabel' => $studentCount > 0 ? 'Update Excel' : CrmMenuLabels::uploadMarksExcel(),
                                            'canRename' => $canRenameExams ?? false,
                                            'examLabel' => (string) ($row['label'] ?? ''),
                                            'canDelete' => ($canDeleteExams ?? false) && ($deleteEligibility[$groupKey]['allowed'] ?? false),
                                            'groupKey' => $groupKey,
                                            'stacked' => false,
                                        ])
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($exams->hasPages())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3 dark:border-white/10">
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
</div>
