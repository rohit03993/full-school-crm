@php
    $steps = [
        1 => ['label' => 'Test & file', 'hint' => 'Name, date, upload'],
        2 => ['label' => 'Map columns', 'hint' => 'Roll + subjects'],
        3 => ['label' => 'Preview', 'hint' => 'Review matches'],
        4 => ['label' => 'Results', 'hint' => 'Import & WhatsApp'],
    ];
@endphp

<div class="mx-auto max-w-4xl space-y-5 pb-24 lg:pb-8">
    @if (filled($importError ?? null))
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            <p class="font-semibold">Import could not finish</p>
            <p class="mt-1">{{ $importError }}</p>
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
        <nav aria-label="Import progress" class="grid gap-3 sm:grid-cols-4">
            @foreach ($steps as $number => $meta)
                @php
                    $isComplete = $step > $number;
                    $isCurrent = $step === $number;
                @endphp
                <div class="flex items-start gap-3">
                    <span @class([
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2',
                        'bg-primary-600 text-white ring-primary-600' => $isCurrent,
                        'bg-emerald-500 text-white ring-emerald-500' => $isComplete,
                        'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-white/10 dark:text-gray-400 dark:ring-white/10' => ! $isCurrent && ! $isComplete,
                    ])>{{ $isComplete ? '✓' : $number }}</span>
                    <div>
                        <p class="text-sm font-semibold">{{ $meta['label'] }}</p>
                        <p class="hidden text-xs text-gray-400 sm:block">{{ $meta['hint'] }}</p>
                    </div>
                </div>
            @endforeach
        </nav>
    </div>

    @if ($step === 1)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-gray-950 dark:text-white">Name your test & upload Excel</h2>
                        <p class="mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                            The <strong>test name</strong> and <strong>date</strong> create the test record. Upload your institute Excel (title row is OK) with <strong>Roll No</strong> and subject columns such as <strong>P, C, M</strong> or full names. Totals / rank / percent columns are ignored.
                        </p>
                    </div>
                    <button type="button" wire:click="downloadTemplate" class="rounded-xl border border-primary-200 px-3.5 py-2 text-sm font-semibold text-primary-700 hover:bg-primary-50 dark:border-primary-500/30 dark:text-primary-300">
                        Download template
                    </button>
                </div>
            </div>

            <div class="grid gap-5 p-4 sm:p-6 lg:grid-cols-2">
                <x-crm.select-input label="Exam type" for="marks-type" wire:model="activityTypeId">
                    <option value="">Select type…</option>
                    @forelse ($activityTypeOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @empty
                        <option value="" disabled>No exam types with marks enabled — edit Exam Types and turn on “Records marks & scores”</option>
                    @endforelse
                </x-crm.select-input>

                <x-crm.text-input label="Test name" model="testName" placeholder="e.g. Unit Test March 2026" />

                <x-crm.text-input label="Test date" model="sessionDate" type="date" />

                <x-crm.text-input label="Default max marks (fallback)" model="defaultMaxMarks" type="number" />

                <x-crm.select-input label="Academic session (optional filter)" for="marks-session" wire:model="academicSessionId" class="lg:col-span-2">
                    <option value="">All active enrollments</option>
                    @foreach ($sessionOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select-input>

                <div class="lg:col-span-2 rounded-xl border border-gray-200 p-4 dark:border-white/10">
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

            <div class="flex justify-end border-t border-gray-100 p-4 dark:border-white/10 sm:px-6">
                <button type="button" wire:click="parseFileAndContinue" wire:loading.attr="disabled" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                    Continue to column mapping
                </button>
            </div>
        </div>
    @endif

    @if ($step === 2)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-lg font-bold">Map columns</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Confirm roll number column and subject columns from your sheet.</p>
            </div>

            <div class="grid gap-5 p-4 sm:p-6">
                <x-crm.select-input label="Roll number column" for="roll-column" wire:model="columnMapping.roll_column">
                    <option value="">Select column…</option>
                    @foreach ($fileHeaders as $index => $header)
                        <option value="{{ $index }}">{{ $header ?: 'Column '.($index + 1) }}</option>
                    @endforeach
                </x-crm.select-input>

                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Subject columns</p>
                    <p class="mt-1 text-xs text-gray-500">Check mark columns and set max marks per subject (Physics 350, Chemistry 200, Maths 500, etc.).</p>
                    <div class="mt-3 grid gap-3">
                        @foreach ($fileHeaders as $index => $header)
                            @if ($index === ($columnMapping['roll_column'] ?? null))
                                @continue
                            @endif
                            @php
                                $isSubject = in_array($index, $columnMapping['subject_columns'] ?? [], true);
                                $resolvedSubject = \App\Support\ExamSubjectCatalog::resolveLabel($header);
                            @endphp
                            <div @class([
                                'rounded-lg border px-3 py-3 dark:border-white/10',
                                'border-primary-200 bg-primary-50/40 dark:border-primary-500/30 dark:bg-primary-500/5' => $isSubject,
                                'border-gray-200' => ! $isSubject,
                            ])>
                                <label class="flex items-start gap-3">
                                    <input
                                        type="checkbox"
                                        value="{{ $index }}"
                                        wire:model.live="columnMapping.subject_columns"
                                        class="mt-1 rounded border-gray-300 text-primary-600"
                                    >
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $header ?: 'Column '.($index + 1) }}</span>
                                        @if ($resolvedSubject !== ($header ?: ''))
                                            <span class="mt-0.5 block text-xs text-gray-500">CRM subject: {{ $resolvedSubject }}</span>
                                        @endif
                                    </span>
                                </label>
                                @if ($isSubject)
                                    <div class="mt-3 pl-7">
                                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400">Max marks for {{ $resolvedSubject }}</label>
                                        <input
                                            type="number"
                                            min="1"
                                            max="9999"
                                            wire:model.live="subjectMaxMarks.{{ $index }}"
                                            class="mt-1 w-full max-w-[10rem] rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900"
                                        >
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex justify-between border-t border-gray-100 p-4 dark:border-white/10 sm:px-6">
                <button type="button" wire:click="$set('step', 1)" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">Back</button>
                <button type="button" wire:click="buildPreview" class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">Preview import</button>
            </div>
        </div>
    @endif

    @if ($step === 3 && is_array($previewPayload))
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-lg font-bold">Preview</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $previewPayload['ready_count'] ?? 0 }} ready,
                    {{ $previewPayload['error_count'] ?? 0 }} with errors.
                    @if (($previewPayload['subject_max_marks'] ?? []) !== [])
                        Max marks:
                        {{ collect($previewPayload['subject_max_marks'])->map(fn ($max, $subject) => $subject.' '.$max)->join(' · ') }}.
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

            <div class="flex justify-between border-t border-gray-100 p-4 dark:border-white/10 sm:px-6">
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

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                    <h3 class="text-base font-bold text-gray-950 dark:text-white">Send marks via WhatsApp</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Uses a pre-approved template. Map template variables to
                        <code class="text-xs">student.name</code>,
                        <code class="text-xs">student.enrollment_number</code>,
                        <code class="text-xs">activity.test_name</code>,
                        <code class="text-xs">activity.marks_summary</code>, etc.
                    </p>
                </div>

                <div class="grid gap-4 p-4 sm:p-6">
                    <x-crm.select-input label="WhatsApp template" for="wa-template" wire:model="whatsappTemplateId">
                        <option value="">Select template…</option>
                        @foreach ($whatsappTemplateOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-crm.select-input>

                    <button type="button" wire:click="queueWhatsAppCampaign" class="justify-self-start rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">
                        Queue WhatsApp to all students with marks
                    </button>
                </div>
            </div>

            <div class="flex justify-start">
                <button type="button" wire:click="startOver" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">
                    Import another sheet
                </button>
            </div>
        </div>
    @endif
</div>
