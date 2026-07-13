@php
    use App\Support\CrmMenuLabels;
@endphp
<div class="space-y-5">
    <div class="overflow-hidden rounded-2xl border border-primary-500/20 bg-gradient-to-r from-primary-500/5 to-white shadow-sm ring-1 ring-gray-950/5 dark:from-primary-500/10 dark:to-gray-900 dark:ring-white/10">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">How exams work</h2>
            <ol class="mt-3 grid gap-3 text-sm sm:grid-cols-4">
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 1 · Create</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        <strong>{{ CrmMenuLabels::createExam() }}</strong> — pick section and exam name; mark sheets are created for every subject.
                    </p>
                </li>
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 2 · Enter marks</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        Teachers enter marks per subject, or use <strong>{{ CrmMenuLabels::uploadMarksExcel() }}</strong> for a spreadsheet.
                    </p>
                </li>
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 3 · Review</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        Tests appear in the table below. <strong>View sheet</strong> to see scores · <strong>Upload again</strong> on a row to update marks for that test.
                    </p>
                </li>
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 4 · Declare</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        <strong>View sheet</strong> → <strong>Publish results online</strong> for the student portal · Super Admin can <strong>issue PDF marksheets</strong>.
                    </p>
                </li>
            </ol>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Daily class roll call stays under <strong>Attendance</strong>.
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

    @if (($matrix['rows'] ?? []) === [])
        <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">No tests yet</p>
            <p class="mt-2">Use <strong>Upload marks (Excel)</strong> in the page header — enter the test name there and upload your sheet.</p>
        </div>
    @else
        <div class="space-y-2 md:hidden">
            @foreach ($matrix['rows'] as $row)
                @php
                    $status = $declarationStatuses[$row['group_key'] ?? ''] ?? ['label' => '—', 'color' => 'gray'];
                    $badgeClass = match ($status['color'] ?? 'gray') {
                        'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                        'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                        'warning' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
                    };
                @endphp
                <div class="rounded-xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['type'] ?? '—' }} · {{ $row['batch'] ?? '—' }} · {{ $row['date']?->format('d M Y') ?? '—' }}
                            </p>
                        </div>
                        @if (($row['tracks_marks'] ?? true) && filled($row['group_key'] ?? null))
                            <span class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ $status['label'] ?? '—' }}
                            </span>
                        @endif
                    </div>
                    @if (! empty($matrix['subjects']))
                        <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                            @foreach ($matrix['subjects'] as $subject)
                                @php
                                    $cell = $row['subjects'][$subject] ?? null;
                                    $count = (int) ($cell['marks_count'] ?? 0);
                                    $present = (int) ($cell['present_count'] ?? 0);
                                @endphp
                                <div class="rounded-lg bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                                    <dt class="truncate text-[10px] font-semibold uppercase text-gray-500">{{ $subject }}</dt>
                                    <dd class="mt-0.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                                        @if (! ($row['tracks_marks'] ?? true))
                                            {{ $present > 0 ? $present.' present' : '—' }}
                                        @elseif ($count > 0)
                                            {{ $count }} scored
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                        @if ($row['tracks_marks'] ?? true)
                            <a href="{{ $reviewPageBaseUrl }}?group={{ urlencode($row['group_key']) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-white/10 dark:text-gray-300">
                                View sheet
                            </a>
                            <a href="{{ \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest($row['label'], $row['activity_type_id'] ?? null, $row['batch_id'] ?? null, $row['date']?->format('Y-m-d')) }}" class="inline-flex min-h-10 flex-1 items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-xs font-semibold text-white hover:bg-primary-500">
                                Upload marks
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden overflow-x-auto rounded-xl ring-1 ring-gray-200 md:block dark:ring-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Test name</th>
                        <th class="px-4 py-2.5">Exam type</th>
                        <th class="px-4 py-2.5">Batch</th>
                        <th class="px-4 py-2.5">Date</th>
                        <th class="px-4 py-2.5">Result</th>
                        @foreach ($matrix['subjects'] as $subject)
                            <th class="px-4 py-2.5 text-center">{{ $subject }}</th>
                        @endforeach
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($matrix['rows'] as $row)
                        <tr class="bg-white dark:bg-gray-900">
                            <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-medium text-gray-950 dark:bg-gray-900 dark:text-white">
                                {{ $row['label'] }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['type'] ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['batch'] ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-400">
                                {{ $row['date']?->format('d M Y') ?? '—' }}
                            </td>
                            @php
                                $status = $declarationStatuses[$row['group_key'] ?? ''] ?? ['label' => '—', 'color' => 'gray'];
                                $badgeClass = match ($status['color'] ?? 'gray') {
                                    'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                    'warning' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                                    default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
                                };
                            @endphp
                            <td class="px-4 py-2.5">
                                @if (($row['tracks_marks'] ?? true) && filled($row['group_key'] ?? null))
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                        {{ $status['label'] ?? '—' }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            @foreach ($matrix['subjects'] as $subject)
                                @php
                                    $cell = $row['subjects'][$subject] ?? null;
                                    $count = (int) ($cell['marks_count'] ?? 0);
                                    $present = (int) ($cell['present_count'] ?? 0);
                                @endphp
                                <td class="px-4 py-2.5 text-center text-xs">
                                    @if (! ($row['tracks_marks'] ?? true))
                                        @if ($present > 0)
                                            <span class="inline-flex rounded-full bg-sky-50 px-2 py-0.5 font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                                                {{ $present }} present
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    @elseif ($count > 0)
                                        <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                            {{ $count }} scored
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="whitespace-nowrap px-4 py-2.5 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($row['tracks_marks'] ?? true)
                                        <a
                                            href="{{ $reviewPageBaseUrl }}?group={{ urlencode($row['group_key']) }}"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                                        >
                                            View sheet
                                        </a>
                                        <a
                                            href="{{ \App\Filament\Pages\BulkActivityMarksImportPage::urlForTest(
                                                $row['label'],
                                                $row['activity_type_id'] ?? null,
                                                $row['batch_id'] ?? null,
                                                $row['date']?->format('Y-m-d'),
                                            ) }}"
                                            class="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-primary-500"
                                        >
                                            Upload marks
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
