@php
    $steps = [
        1 => ['label' => 'Context & file', 'hint' => 'Session, course, upload'],
        2 => ['label' => 'Map columns', 'hint' => 'Match spreadsheet headers'],
        3 => ['label' => 'Preview', 'hint' => 'Review & duplicates'],
        4 => ['label' => 'Results', 'hint' => 'Import summary'],
    ];

    $requiredColumns = ['Roll number', 'Student name', 'Batch name'];
    $optionalColumns = ['Primary mobile', "Father's name", 'Date of birth', 'Gender'];
@endphp

<div class="mx-auto max-w-4xl space-y-5 pb-24 lg:pb-8">
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-semibold">Migration import only</p>
        <p class="mt-1">Use this screen to bring in existing students from a spreadsheet. For new students, use Search Student → Convert to Admission so enquiry and call history stay intact.</p>
    </div>

    @if (filled($importError ?? null))
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-200">
            <p class="font-semibold">Import could not finish</p>
            <p class="mt-1">{{ $importError }}</p>
        </div>
    @endif

    @if ($isImporting ?? false)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20">
                    <svg class="h-6 w-6 animate-spin text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <h3 class="mt-4 text-lg font-bold text-gray-950 dark:text-white">Importing students…</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    {{ number_format($importProcessed ?? 0) }} of {{ number_format($importTotal ?? 0) }} students processed
                </p>
                <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all duration-300 ease-out dark:bg-primary-500"
                        style="width: {{ max(4, $importProgressPercent ?? 0) }}%"
                        role="progressbar"
                        aria-valuenow="{{ $importProgressPercent ?? 0 }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></div>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ $importProgressPercent ?? 0 }}% complete — please keep this tab open until finished.
                </p>
            </div>
        </div>
    @endif

    {{-- Stepper --}}
    <div class="overflow-hidden rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
        <nav aria-label="Import progress" class="relative isolate grid gap-3 sm:grid-cols-4">
            @foreach ($steps as $number => $meta)
                @php
                    $isComplete = $step > $number;
                    $isCurrent = $step === $number;
                @endphp
                <div class="relative flex items-start gap-3">
                    @if ($number < count($steps))
                        <span @class([
                            'pointer-events-none absolute left-8 top-4 z-0 hidden h-px w-[calc(100%-2rem)] sm:block',
                            'bg-primary-300 dark:bg-primary-600' => $isComplete,
                            'bg-gray-200 dark:bg-white/10' => ! $isComplete,
                        ]) aria-hidden="true"></span>
                    @endif

                    <span @class([
                        'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2',
                        'bg-primary-600 text-white ring-primary-600' => $isCurrent,
                        'bg-emerald-500 text-white ring-emerald-500' => $isComplete,
                        'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-white/10 dark:text-gray-400 dark:ring-white/10' => ! $isCurrent && ! $isComplete,
                    ])>
                        @if ($isComplete)
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        @else
                            {{ $number }}
                        @endif
                    </span>

                    <div class="min-w-0 pt-0.5">
                        <p @class([
                            'text-sm font-semibold',
                            'text-primary-700 dark:text-primary-300' => $isCurrent,
                            'text-emerald-700 dark:text-emerald-300' => $isComplete,
                            'text-gray-500 dark:text-gray-400' => ! $isCurrent && ! $isComplete,
                        ])>{{ $meta['label'] }}</p>
                        <p class="hidden text-xs text-gray-400 dark:text-gray-500 sm:block">{{ $meta['hint'] }}</p>
                    </div>
                </div>
            @endforeach
        </nav>
    </div>

    @if ($step === 1)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 bg-gradient-to-r from-primary-500/10 via-primary-500/5 to-transparent px-4 py-4 dark:border-white/10 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-sm">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                        </span>
                        <div>
                            <h2 class="text-lg font-bold text-gray-950 dark:text-white">Import enrolled students</h2>
                            <p class="mt-1 max-w-xl text-sm text-gray-600 dark:text-gray-400">
                                Upload a spreadsheet with roll number, student name, and batch name (e.g. Class (Course) column). Course and session come from the matched CRM batch — create batches under Academics first.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="downloadTemplate"
                        class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-white px-3.5 py-2 text-sm font-semibold text-primary-700 shadow-sm transition hover:bg-primary-50 dark:border-primary-500/30 dark:bg-white/5 dark:text-primary-300 dark:hover:bg-white/10"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download template
                    </button>
                </div>
            </div>

            <div class="fi-crm-form grid gap-6 border-t border-transparent p-4 pt-6 sm:p-6 sm:pt-6 lg:grid-cols-5">
                <div class="space-y-5 lg:col-span-3">
                    <x-crm.select-input
                        label="Limit batch lookup to session (optional)"
                        for="import-session"
                        hint="Leave as current session, or pick All sessions by clearing this after upload if batches span years."
                        wire:model.live="academicSessionId"
                    >
                        <option value="">All active sessions</option>
                        @foreach ($sessionOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </x-crm.select-input>

                    <div class="rounded-xl border border-primary-200 bg-primary-50/60 px-4 py-3 text-sm text-primary-950 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-100">
                        <p class="font-semibold">Batch names must exist in CRM</p>
                        <p class="mt-1">Create batches under <span class="font-semibold">Academics → Batches</span> with the same names as your Excel column (e.g. <span class="font-mono text-xs">12th JEE Batch C (2026-27)</span>). Each batch already links to its course and session.</p>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Excel or CSV file</label>
                    <label
                        @class([
                            'group relative flex min-h-[11rem] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-4 py-6 text-center transition',
                            'border-primary-400 bg-primary-50/50 dark:border-primary-500/40 dark:bg-primary-500/5' => filled($uploadFileName),
                            'border-gray-300 bg-gray-50/80 hover:border-primary-400 hover:bg-primary-50/30 dark:border-white/15 dark:bg-white/5 dark:hover:border-primary-500/40' => blank($uploadFileName),
                        ])
                    >
                        <input type="file" wire:model="uploadFile" accept=".csv,.xlsx,.xls,.txt" class="sr-only">

                        @if (filled($uploadFileName))
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </span>
                            <p class="mt-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $uploadFileName }}</p>
                            <p class="mt-1 text-xs text-primary-700 dark:text-primary-300">Click to replace file</p>
                        @else
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm ring-1 ring-gray-200 transition group-hover:text-primary-600 dark:bg-gray-900 dark:ring-white/10 dark:group-hover:text-primary-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </span>
                            <p class="mt-3 text-sm font-semibold text-gray-950 dark:text-white">Drop file here or click to browse</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">.xlsx, .xls, .csv — max {{ number_format($maxRows) }} rows</p>
                        <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
                            <span class="font-semibold">Important:</span> Upload the original <span class="font-semibold">.xlsx</span> file. If you save as CSV, Excel turns mobiles into <span class="font-mono">9.18E+11</span> and digits are lost. In Excel: select WhatsApp column → Format Cells → Text → then save as .xlsx.
                        </p>
                        @endif

                        <span wire:loading wire:target="uploadFile" class="absolute inset-0 flex items-center justify-center rounded-2xl bg-white/80 text-sm font-medium text-primary-700 dark:bg-gray-900/80 dark:text-primary-300">
                            Uploading…
                        </span>
                    </label>

                    <div class="mt-4 rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Required columns</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($requiredColumns as $column)
                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-white/10">{{ $column }}</span>
                            @endforeach
                        </div>
                        <p class="mt-3 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Optional (staff can edit later)</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($optionalColumns as $column)
                                <span class="rounded-full bg-transparent px-2.5 py-1 text-xs font-medium text-gray-500 ring-1 ring-dashed ring-gray-300 dark:text-gray-400 dark:ring-white/15">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:justify-end sm:px-6">
                <button
                    type="button"
                    wire:click="parseFileAndContinue"
                    wire:loading.attr="disabled"
                    wire:target="parseFileAndContinue,uploadFile"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="parseFileAndContinue,uploadFile">Continue to column mapping</span>
                    <span wire:loading wire:target="parseFileAndContinue,uploadFile">Processing file…</span>
                    <svg wire:loading.remove wire:target="parseFileAndContinue,uploadFile" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>
        </div>
    @endif

    @if ($step === 2)
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Map spreadsheet columns</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Headers were auto-matched. Change any mapping that looks wrong before previewing.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">File column</th>
                            <th class="px-4 py-3">Sample from row 1</th>
                            <th class="px-4 py-3">Maps to CRM field</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($fileHeaders as $index => $header)
                            @if (blank($header))
                                @continue
                            @endif
                            @php
                                $mappedField = $columnMapping[$index] ?? 'skip';
                                $isRequired = in_array($mappedField, ['roll_number', 'name'], true);
                            @endphp
                            <tr class="transition hover:bg-gray-50/80 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $header }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $fileRows[0][$index] ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                                        <x-crm.select
                                            wire:model="columnMapping.{{ $index }}"
                                            class="min-w-[14rem]"
                                        >
                                            @foreach ($fieldLabels as $fieldKey => $fieldLabel)
                                                <option value="{{ $fieldKey }}">{{ $fieldLabel }}</option>
                                            @endforeach
                                        </x-crm.select>
                                        @if ($mappedField !== 'skip')
                                            <span @class([
                                                'inline-flex w-fit rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                                'bg-primary-100 text-primary-800 dark:bg-primary-500/15 dark:text-primary-300' => $isRequired,
                                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400' => ! $isRequired,
                                            ])>{{ $isRequired ? 'Required' : 'Optional' }}</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:justify-between sm:px-6">
                <button type="button" wire:click="goToStep(1)" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                    Back
                </button>
                <button
                    type="button"
                    wire:click="buildPreview"
                    wire:loading.attr="disabled"
                    wire:target="buildPreview"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-500 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="buildPreview">Preview rows</span>
                    <span wire:loading wire:target="buildPreview">Building preview…</span>
                    <svg wire:loading.remove wire:target="buildPreview" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </button>
            </div>
        </div>
    @endif

    @if ($step === 3)
        @php
            $allPreviewRows = $allPreviewRows ?? $previewRows;
            $readyCount = collect($allPreviewRows)->where('status', 'ready')->count();
            $duplicateCount = collect($allPreviewRows)->where('status', 'duplicate')->count();
            $errorCount = collect($allPreviewRows)->where('status', 'error')->count();
            $noMobileCount = collect($allPreviewRows)->filter(fn (array $row): bool => ($row['status'] ?? '') === 'ready' && ! empty($row['warnings'] ?? []))->count();
            $previewStatusFilter = $previewStatusFilter ?? 'all';
            $filterLabels = [
                'all' => 'All rows',
                'ready' => 'Ready only',
                'no_mobile' => 'No mobile only',
                'duplicate' => 'Duplicates only',
                'error' => 'Errors only',
            ];
        @endphp

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            @foreach ([
                ['key' => 'ready', 'label' => 'Ready', 'value' => $readyCount, 'tone' => 'text-emerald-600 dark:text-emerald-400'],
                ['key' => 'no_mobile', 'label' => 'No mobile', 'value' => $noMobileCount, 'tone' => 'text-amber-600 dark:text-amber-400'],
                ['key' => 'duplicate', 'label' => 'Duplicates', 'value' => $duplicateCount, 'tone' => 'text-amber-600 dark:text-amber-400'],
                ['key' => 'error', 'label' => 'Errors', 'value' => $errorCount, 'tone' => 'text-danger-600 dark:text-danger-400'],
                ['key' => 'all', 'label' => 'Will import', 'value' => $importableCount, 'tone' => 'text-primary-600 dark:text-primary-400'],
            ] as $stat)
                <button
                    type="button"
                    wire:click="setPreviewStatusFilter('{{ $stat['key'] }}')"
                    @class([
                        'rounded-xl px-3 py-3 text-left shadow-sm ring-1 transition',
                        'bg-white ring-gray-950/5 hover:ring-primary-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-primary-500/40' => $previewStatusFilter !== $stat['key'],
                        'bg-primary-50 ring-primary-400 dark:bg-primary-500/10 dark:ring-primary-500/50' => $previewStatusFilter === $stat['key'],
                    ])
                >
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p @class(['mt-1 text-2xl font-bold', $stat['tone']])>{{ $stat['value'] }}</p>
                    @if ($previewStatusFilter === $stat['key'])
                        <p class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Filtered</p>
                    @endif
                </button>
            @endforeach
        </div>

        @if ($errorCount > 0)
            <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-4 dark:border-danger-500/30 dark:bg-danger-500/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-bold text-danger-900 dark:text-danger-200">
                        {{ $errorCount }} row{{ $errorCount === 1 ? '' : 's' }} will not import
                    </h3>
                    @if ($previewStatusFilter !== 'error')
                        <button
                            type="button"
                            wire:click="setPreviewStatusFilter('error')"
                            class="text-xs font-semibold text-danger-700 underline decoration-danger-300 underline-offset-2 hover:text-danger-900 dark:text-danger-300"
                        >
                            Show errors only
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="setPreviewStatusFilter('all')"
                            class="text-xs font-semibold text-danger-700 underline decoration-danger-300 underline-offset-2 hover:text-danger-900 dark:text-danger-300"
                        >
                            Show all rows
                        </button>
                    @endif
                </div>
                <ul class="mt-3 max-h-48 space-y-2 overflow-auto">
                    @foreach ($previewErrorRows ?? [] as $errorRow)
                        <li class="rounded-lg bg-white/80 px-3 py-2 text-xs text-danger-900 ring-1 ring-danger-100 dark:bg-gray-900/60 dark:text-danger-100 dark:ring-danger-500/20">
                            <span class="font-mono font-bold">Row {{ $errorRow['row_number'] }}</span>
                            · {{ $errorRow['data']['name'] ?? '—' }}
                            @if (filled($errorRow['data']['roll_number'] ?? null))
                                · Roll {{ $errorRow['data']['roll_number'] }}
                            @endif
                            <p class="mt-1 text-danger-700 dark:text-danger-300">{{ implode(' ', $errorRow['errors'] ?? []) }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($previewStatusFilter !== 'all')
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Showing <span class="font-semibold text-gray-950 dark:text-white">{{ count($previewRows) }}</span>
                {{ $filterLabels[$previewStatusFilter] ?? 'rows' }}.
                <button type="button" wire:click="setPreviewStatusFilter('all')" class="font-semibold text-primary-700 underline underline-offset-2 dark:text-primary-300">Clear filter</button>
            </p>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-lg font-bold text-gray-950 dark:text-white">Review rows before import</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Each row is matched to a CRM batch by name. Course and session are taken from that batch. Invalid or missing mobiles still import without a number.
                </p>
            </div>

            <div class="max-h-[32rem] overflow-auto">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Row</th>
                            <th class="px-4 py-3">Roll</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Mobile</th>
                            <th class="px-4 py-3">CRM batch</th>
                            <th class="px-4 py-3">Course</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">If duplicate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($previewRows as $row)
                            <tr @class([
                                'transition',
                                'bg-danger-50/40 dark:bg-danger-500/5' => ($row['status'] ?? '') === 'error',
                                'bg-amber-50/40 dark:bg-amber-500/5' => ($row['status'] ?? '') === 'duplicate',
                            ])>
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row['row_number'] }}</td>
                                <td class="px-4 py-3 font-mono font-semibold text-gray-950 dark:text-white">{{ $row['data']['roll_number'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-950 dark:text-white">{{ $row['data']['name'] ?? '—' }}</td>
                                <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                                    @if (filled($row['data']['mobile'] ?? null))
                                        {{ $row['data']['mobile'] }}
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    @if ($row['resolved_batch']['name'] ?? null)
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $row['resolved_batch']['name'] }}</span>
                                    @else
                                        <span class="text-danger-600 dark:text-danger-400">{{ $row['data']['batch_section'] ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $row['resolved_batch']['course_name'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['status'] === 'ready')
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Ready</span>
                                        @if (! empty($row['warnings'] ?? []))
                                            <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">{{ implode(' ', $row['warnings']) }}</p>
                                        @endif
                                    @elseif ($row['status'] === 'duplicate')
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Duplicate mobile</span>
                                        @if ($row['existing_student'] ?? null)
                                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                                CRM: {{ $row['existing_student']['name'] }}
                                                @if ($row['existing_student']['roll_number'] ?? null)
                                                    · Roll {{ $row['existing_student']['roll_number'] }}
                                                @endif
                                            </p>
                                        @endif
                                    @else
                                        <span class="inline-flex rounded-full bg-danger-100 px-2.5 py-0.5 text-xs font-semibold text-danger-800 dark:bg-danger-500/15 dark:text-danger-300">Error</span>
                                        <p class="mt-1.5 text-xs text-danger-600 dark:text-danger-400">{{ implode(' ', $row['errors'] ?? []) }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($row['status'] === 'duplicate')
                                        <x-crm.select wire:model.live="duplicateResolutions.{{ $row['row_number'] }}" class="min-w-[12rem] text-xs">
                                            @foreach ($duplicateOptions as $option)
                                                <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                            @endforeach
                                        </x-crm.select>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No rows match this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:justify-between sm:px-6">
                <button type="button" wire:click="goToStep(2)" @disabled($isImporting ?? false) class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">
                    Back
                </button>
                <button
                    type="button"
                    wire:click="runImport"
                    wire:loading.attr="disabled"
                    wire:target="runImport"
                    @disabled(($importableCount === 0) || ($isImporting ?? false))
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    @if ($isImporting ?? false)
                        <span>Importing {{ $importProgressPercent ?? 0 }}%…</span>
                    @else
                        <span wire:loading.remove wire:target="runImport">Import {{ $importableCount }} student{{ $importableCount === 1 ? '' : 's' }}</span>
                        <span wire:loading wire:target="runImport">Starting import…</span>
                    @endif
                </button>
            </div>
        </div>
    @endif

    @if ($step === 4 && $importResult)
        @php
            $hasFailures = ($importResult['failed'] ?? 0) > 0;
        @endphp

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div @class([
                'border-b px-4 py-6 text-center sm:px-6',
                'border-emerald-100 bg-gradient-to-b from-emerald-50 to-transparent dark:border-emerald-500/20 dark:from-emerald-500/10' => ! $hasFailures,
                'border-amber-100 bg-gradient-to-b from-amber-50 to-transparent dark:border-amber-500/20 dark:from-amber-500/10' => $hasFailures,
            ])>
                <span @class([
                    'mx-auto flex h-14 w-14 items-center justify-center rounded-full',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => ! $hasFailures,
                    'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300' => $hasFailures,
                ])>
                    @if ($hasFailures)
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    @else
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @endif
                </span>
                <h2 class="mt-4 text-xl font-bold text-gray-950 dark:text-white">
                    {{ $hasFailures ? 'Import finished with issues' : 'Import complete' }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ ($importResult['created'] ?? 0) + ($importResult['updated'] ?? 0) }} student(s) processed successfully.
                    @if (($importResult['without_mobile'] ?? 0) > 0)
                        {{ $importResult['without_mobile'] }} imported without a mobile number — see All Students → filter “Missing mobile / import issue”.
                    @endif
                    @if (($importResult['preview_rejected'] ?? 0) > 0)
                        {{ $importResult['preview_rejected'] }} row(s) were skipped from the file due to validation issues.
                    @endif
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-6 sm:p-6">
                @foreach ([
                    ['Created', $importResult['created'] ?? 0, 'text-emerald-700 dark:text-emerald-300', 'bg-emerald-50 dark:bg-emerald-500/10'],
                    ['Updated', $importResult['updated'] ?? 0, 'text-primary-700 dark:text-primary-300', 'bg-primary-50 dark:bg-primary-500/10'],
                    ['No mobile', $importResult['without_mobile'] ?? 0, 'text-amber-700 dark:text-amber-300', 'bg-amber-50 dark:bg-amber-500/10'],
                    ['Skipped', $importResult['skipped'] ?? 0, 'text-gray-700 dark:text-gray-300', 'bg-gray-50 dark:bg-white/5'],
                    ['File rejected', $importResult['preview_rejected'] ?? 0, 'text-amber-700 dark:text-amber-300', 'bg-amber-50 dark:bg-amber-500/10'],
                    ['Failed', $importResult['failed'] ?? 0, 'text-danger-700 dark:text-danger-300', 'bg-danger-50 dark:bg-danger-500/10'],
                ] as [$label, $value, $tone, $bg])
                    <div @class(['rounded-xl px-3 py-4', $bg])>
                        <dt class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd @class(['mt-1 text-3xl font-bold', $tone])>{{ $value }}</dd>
                    </div>
                @endforeach
            </div>

            @if (! empty($importResult['errors']))
                <div class="border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Failed rows</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($importResult['errors'] as $error)
                            <li class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">
                                <span class="font-semibold">Row {{ $error['row'] }}:</span> {{ $error['message'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <button type="button" wire:click="startOver" class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-500">
                    Import another file
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>
