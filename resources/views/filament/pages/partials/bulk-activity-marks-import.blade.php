@php
    $steps = [
        1 => ['label' => 'Exam & file', 'hint' => 'Name, date, upload'],
        2 => ['label' => 'Map columns', 'hint' => 'Roll + subjects'],
        3 => ['label' => 'Preview', 'hint' => 'Review matches'],
        4 => ['label' => 'Results', 'hint' => 'Import & WhatsApp'],
    ];
@endphp

<div @class([
    'mx-auto max-w-4xl space-y-4 sm:space-y-5',
    'crm-sticky-above-nav-pad' => ($step ?? 1) < 4,
    'pb-24 lg:pb-8' => ($step ?? 1) >= 4,
])>
    @if (filled($importError ?? null))
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-3 py-2.5 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200 sm:px-4 sm:py-3">
            <p class="font-semibold">Import could not finish</p>
            <p class="mt-1">{{ $importError }}</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
        <nav aria-label="Import progress">
            <div class="flex items-center justify-between gap-1 sm:grid sm:grid-cols-4 sm:gap-3">
                @foreach ($steps as $number => $meta)
                    @php
                        $isComplete = $step > $number;
                        $isCurrent = $step === $number;
                    @endphp
                    <div class="flex min-w-0 flex-col items-center gap-1 sm:flex-row sm:items-start sm:gap-3">
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold ring-2 sm:h-8 sm:w-8 sm:text-xs',
                            'bg-primary-600 text-white ring-primary-600' => $isCurrent,
                            'bg-emerald-500 text-white ring-emerald-500' => $isComplete,
                            'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-white/10 dark:text-gray-400 dark:ring-white/10' => ! $isCurrent && ! $isComplete,
                        ])>{{ $isComplete ? '✓' : $number }}</span>
                        <div class="hidden min-w-0 sm:block">
                            <p class="text-sm font-semibold">{{ $meta['label'] }}</p>
                            <p class="text-xs text-gray-400">{{ $meta['hint'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-center text-xs font-medium text-gray-500 sm:hidden dark:text-gray-400">
                {{ $steps[$step]['label'] ?? '' }}
            </p>
        </nav>
    </div>

    @if ($step === 1)
        <div class="space-y-3">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-end border-b border-gray-100 px-3 py-2.5 dark:border-white/10 sm:px-6 sm:py-3">
                    <button type="button" wire:click="downloadTemplate" class="rounded-lg border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50 dark:border-primary-500/30 dark:text-primary-300 sm:rounded-xl sm:px-3.5 sm:py-2 sm:text-sm">
                        Download template
                    </button>
                </div>

                <div class="grid gap-4 p-3 sm:gap-5 sm:p-6 lg:grid-cols-2">
                @if ($lockExamIdentity ?? false)
                    <div class="lg:col-span-2 rounded-xl bg-gray-50 px-3 py-2.5 text-sm dark:bg-white/5 sm:px-4 sm:py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Exam</p>
                        <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $testName }}</p>
                        <p class="mt-0.5 text-gray-600 dark:text-gray-400">
                            {{ $activityTypeOptions[$activityTypeId] ?? 'Exam' }}
                            @if (filled($sessionDate))
                                · {{ \Illuminate\Support\Carbon::parse($sessionDate)->format('d M Y') }}
                            @endif
                        </p>
                        <p class="mt-2 text-xs text-gray-500">To change the title, go back and use Rename. Date and type stay with this exam.</p>
                    </div>
                @else
                    <x-crm.select-input label="Exam type" for="marks-type" wire:model="activityTypeId">
                        <option value="">Select type…</option>
                        @forelse ($activityTypeOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @empty
                            <option value="" disabled>No exam types with marks enabled — edit Exam Types and turn on “Records marks & scores”</option>
                        @endforelse
                    </x-crm.select-input>

                    <x-crm.text-input label="Exam name" model="testName" placeholder="e.g. Unit Test March 2026" />

                    <x-crm.text-input label="Exam date" model="sessionDate" type="date" />
                @endif

                <div>
                    <x-crm.text-input label="Starting out of" model="defaultMaxMarks" type="number" />
                    <p class="mt-1 text-xs text-gray-500">Starting value only. On the next step you set 180, 100, 50, or any number per subject for this exam.</p>
                </div>

                <x-crm.select-input label="Academic session (optional filter)" for="marks-session" wire:model="academicSessionId" class="lg:col-span-2">
                    <option value="">All active enrollments</option>
                    @foreach ($sessionOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select-input>

                <div class="lg:col-span-2 rounded-xl border border-gray-200 p-3 dark:border-white/10 sm:p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model.live="limitToBatch" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span>
                            <span class="block text-sm font-semibold text-gray-950 dark:text-white">Limit to one batch only</span>
                            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">Leave unchecked to import for all students matched by roll number across batches.</span>
                        </span>
                    </label>

                    @if ($limitToBatch)
                        <div class="mt-4">
                            <x-crm.select-input label="Batch" for="marks-batch" wire:model="batchId">
                                <option value="">Select batch…</option>
                                @foreach ($batchOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </x-crm.select-input>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-sm font-semibold text-gray-950 dark:text-white">Marks file</label>
                    <input type="file" wire:model="uploadFile" accept=".csv,.txt,.xlsx,.xls" class="mt-2 block w-full text-sm">
                    <p class="mt-1 text-xs text-gray-500">CSV or Excel, up to {{ number_format($maxRows) }} rows.</p>
                </div>
            </div>
            </div>

            <div class="crm-sticky-above-nav crm-sticky-above-nav--in-flow flex justify-end rounded-xl border border-gray-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-gray-900 sm:px-4">
                <button type="button" wire:click="parseFileAndContinue" wire:loading.attr="disabled" class="inline-flex min-h-10 w-full items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500 sm:w-auto">
                    <span class="sm:hidden">Continue</span>
                    <span class="hidden sm:inline">Continue to column mapping</span>
                </button>
            </div>
        </div>
    @endif

    @if ($step === 2)
        <div class="space-y-3">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-3 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h2 class="text-base font-bold sm:text-lg">Map columns</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Roll number is mapped automatically when the file has a Roll No column
                    (S.No is ignored). Tick subject columns, then set <strong>Out of</strong> for this test
                    (100, 180, 50 — whatever the paper was). That value is saved with the exam.
                </p>
            </div>

            <div class="grid gap-4 p-3 sm:gap-5 sm:p-6">
                @php
                    $rollColumn = $columnMapping['roll_column'] ?? null;
                    $hasRollColumn = $rollColumn !== null && $rollColumn !== '';
                    $selectedColumns = array_map('intval', $columnMapping['subject_columns'] ?? []);
                @endphp

                <x-crm.select-input label="Roll number column" for="roll-column" wire:model.live="columnMapping.roll_column">
                    <option value="">Select column…</option>
                    @foreach ($fileHeaders as $index => $header)
                        <option value="{{ $index }}" @selected($hasRollColumn && (int) $index === (int) $rollColumn)>{{ $header ?: 'Column '.($index + 1) }}</option>
                    @endforeach
                </x-crm.select-input>

                <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2.5">Use</th>
                                <th class="px-3 py-2.5">Excel column</th>
                                <th class="px-3 py-2.5">Subject name</th>
                                <th class="px-3 py-2.5">Out of (this test)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($fileHeaders as $index => $header)
                                @if ($hasRollColumn && (int) $index === (int) $rollColumn)
                                    @continue
                                @endif
                                @php
                                    $isSubject = in_array((int) $index, $selectedColumns, true);
                                    $suggested = \App\Support\ExamSubjectCatalog::resolveLabel($header);
                                @endphp
                                <tr
                                    wire:key="marks-map-col-{{ $index }}"
                                    @class([
                                        'bg-primary-50/40 dark:bg-primary-500/5' => $isSubject,
                                        'bg-white dark:bg-gray-900' => ! $isSubject,
                                    ])
                                >
                                    <td class="px-3 py-2.5 align-top">
                                        <input
                                            type="checkbox"
                                            value="{{ $index }}"
                                            wire:model.live="columnMapping.subject_columns"
                                            class="rounded border-gray-300 text-primary-600"
                                        >
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        <p class="font-semibold text-gray-950 dark:text-white">{{ $header ?: 'Column '.($index + 1) }}</p>
                                        @if ($suggested !== ($header ?: '') && $suggested !== 'Subject')
                                            <p class="mt-0.5 text-xs text-gray-500">Suggested: {{ $suggested }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        @if ($isSubject)
                                            <input
                                                type="text"
                                                wire:model.blur="subjectLabels.{{ $index }}"
                                                class="w-full min-w-[10rem] rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                                            >
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 align-top">
                                        @if ($isSubject)
                                            <input
                                                type="number"
                                                min="1"
                                                max="9999"
                                                step="0.01"
                                                wire:model.blur="subjectMaxMarks.{{ $index }}"
                                                class="w-28 rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                                                placeholder="{{ (int) $defaultMaxMarks }}"
                                            >
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

            <div class="crm-sticky-above-nav crm-sticky-above-nav--in-flow flex justify-between gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-gray-900 sm:px-4">
                <button type="button" wire:click="$set('step', 1)" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Back</button>
                <button type="button" wire:click="buildPreview" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Preview import</button>
            </div>
        </div>
    @endif

    @if ($step === 3 && is_array($previewPayload))
        <div class="space-y-3">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-3 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h2 class="text-base font-bold sm:text-lg">Preview</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $previewPayload['ready_count'] ?? 0 }} ready,
                    {{ $previewPayload['error_count'] ?? 0 }} with errors.
                    @if (($previewPayload['subject_max_marks'] ?? []) !== [])
                        This test:
                        {{ collect($previewPayload['subject_max_marks'])->map(fn ($max, $subject) => $subject.' out of '.$max)->join(' · ') }}.
                    @endif
                    @if (($previewPayload['batches'] ?? []) !== [])
                        Batches: {{ collect($previewPayload['batches'])->map(fn ($b) => $b['name'].' ('.$b['count'].')')->join(', ') }}.
                    @endif
                </p>
            </div>

            <div class="max-h-[28rem] overflow-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Row</th>
                            <th class="px-4 py-2 text-left font-semibold">Roll</th>
                            <th class="px-4 py-2 text-left font-semibold">Student</th>
                            <th class="px-4 py-2 text-left font-semibold">Batch</th>
                            @foreach ($previewPayload['subjects'] ?? [] as $subject)
                                <th class="px-4 py-2 text-left font-semibold">{{ $subject }}</th>
                            @endforeach
                            <th class="px-4 py-2 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($previewPayload['rows'] ?? [] as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-2 font-mono">{{ $row['roll_number'] }}</td>
                                <td class="px-4 py-2">{{ $row['student_name'] ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $row['batch_name'] ?? '—' }}</td>
                                @foreach ($previewPayload['subjects'] ?? [] as $subject)
                                    @php
                                        $mark = $row['subject_marks'][$subject] ?? null;
                                        $max = $previewPayload['subject_max_marks'][$subject] ?? null;
                                    @endphp
                                    <td class="px-4 py-2 font-mono text-xs">
                                        @if ($mark !== null)
                                            {{ rtrim(rtrim(number_format((float) $mark, 2), '0'), '.') }}@if ($max) / {{ rtrim(rtrim(number_format((float) $max, 2), '0'), '.') }}@endif
                                        @else
                                            <span class="text-gray-400">Absent</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-2">
                                    @if (($row['status'] ?? '') === 'ready')
                                        <span class="text-emerald-600">Ready</span>
                                    @else
                                        <span class="text-danger-600">{{ implode(' ', $row['errors'] ?? []) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>

            <div class="crm-sticky-above-nav crm-sticky-above-nav--in-flow flex justify-between gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-gray-900 sm:px-4">
                <button type="button" wire:click="$set('step', 2)" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Back</button>
                <button type="button" wire:click="runImport" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                    Import {{ $previewPayload['ready_count'] ?? 0 }} row(s)
                </button>
            </div>
        </div>
    @endif

    @if ($step === 4 && is_array($importResult))
        <div class="space-y-4">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                    <h2 class="text-lg font-bold text-emerald-700 dark:text-emerald-300">Import complete</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $importResult['marks_saved'] ?? 0 }} mark(s) saved for {{ $importResult['students'] ?? 0 }} student(s)
                        across {{ $importResult['batches'] ?? 0 }} batch(es).
                        Sessions created: {{ $importResult['sessions_created'] ?? 0 }},
                        updated: {{ $importResult['sessions_updated'] ?? 0 }}.
                    </p>
                </div>

                @if (($importResult['errors'] ?? []) !== [])
                    <div class="border-t border-gray-100 p-4 text-sm text-danger-600 dark:border-white/10">
                        @foreach ($importResult['errors'] as $error)
                            <p>{{ $error['message'] ?? '' }}</p>
                        @endforeach
                    </div>
                @endif
            </div>

            @include('filament.pages.partials.exam-marks-whatsapp-send', [
                'canSendWhatsApp' => $canSendWhatsApp ?? false,
                'defaultMarksTemplateName' => $defaultMarksTemplateName ?? null,
                'examMarksAutomationsUrl' => $examMarksAutomationsUrl ?? null,
                'whatsappTemplateOptions' => $whatsappTemplateOptions ?? [],
                'whatsappTemplateInputId' => 'wa-template',
            ])

            <div class="flex justify-start">
                <button type="button" wire:click="startOver" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">
                    Import another sheet
                </button>
            </div>
        </div>
    @endif
</div>
