<div class="space-y-5">
    <div class="overflow-hidden rounded-2xl border border-primary-500/20 bg-gradient-to-r from-primary-500/5 to-white shadow-sm ring-1 ring-gray-950/5 dark:from-primary-500/10 dark:to-gray-900 dark:ring-white/10">
        <div class="px-4 py-5 sm:px-6">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">How test marks work</h2>
            <ol class="mt-3 grid gap-3 text-sm sm:grid-cols-4">
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 1 · Setup (once)</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        <strong>Exam Types</strong> — add categories (Unit Test, Practical, Mock). Turn <strong>Marks ✓</strong> on for types you will score.
                    </p>
                </li>
                <li class="rounded-xl bg-white/80 px-3 py-3 ring-1 ring-gray-200 dark:bg-gray-900/80 dark:ring-white/10">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">Step 2 · Upload</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        Click <strong>Upload marks (Excel)</strong> above. Enter the <strong>test name</strong> and date, pick exam type, upload the file — all subjects are created together.
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
                <strong>Workshops &amp; events</strong> — use sidebar <strong>Workshops &amp; Events</strong> (not Excel upload).
                Daily class roll call stays under <strong>Attendance</strong>.
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <h2 class="text-lg font-bold text-gray-950 dark:text-white">All tests &amp; activities</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Exam rows show mark counts. Workshop and event rows use <strong>Mark attendance</strong> instead of Excel upload.
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
        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
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
                                    @elseif (filled($row['session_id'] ?? null))
                                        <a
                                            href="{{ \App\Filament\Pages\SessionAttendancePage::getUrl()
                                                .'?activity_type_id='.urlencode((string) ($row['activity_type_id'] ?? ''))
                                                .'&batch_id='.urlencode((string) ($row['batch_id'] ?? ''))
                                                .'&session_date='.urlencode($row['date']?->format('Y-m-d') ?? '')
                                                .'&session_title='.urlencode($row['label']) }}"
                                            class="rounded-lg bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-sky-500"
                                        >
                                            Mark attendance
                                        </a>
                                        <a
                                            href="{{ \App\Filament\Resources\ActivitySessions\ActivitySessionResource::getUrl('edit', ['record' => $row['session_id']]) }}"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                                        >
                                            Edit
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
