@php
    $pickedCount = ($reportMode ?? 'one') === 'one'
        ? (($singleStudentId ?? '') !== '' ? 1 : 0)
        : count($selectedStudentIds ?? []);
    $singleStudent = ($reportMode ?? '') === 'one'
        ? ($bulkStudents ?? collect())->firstWhere('id', (int) ($singleStudentId ?? 0))
        : null;
    $confirmWho = $pickedCount === 1
        ? ($singleStudent?->name ?: '1 student')
        : $pickedCount.' students';
    $saveLabel = $pickedCount > 1 ? 'Save reports' : 'Save report';
    $saveAction = ($reportMode ?? 'one') === 'one' ? 'saveSingleReport' : 'sendBulkReport';
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-4xl lg:pb-6">
    <div class="grid grid-cols-3 gap-2">
        @foreach ([
            'one' => 'One student',
            'many' => 'Many students',
            'saved' => 'Saved reports',
        ] as $mode => $label)
            <button
                type="button"
                wire:click="setReportMode('{{ $mode }}')"
                @class([
                    'min-h-11 rounded-xl px-2 text-center text-xs font-semibold sm:text-sm',
                    'bg-rose-600 text-white' => ($reportMode ?? 'one') === $mode,
                    'bg-white text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10' => ($reportMode ?? 'one') !== $mode,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>

    @if (($reportMode ?? 'one') === 'saved')
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
            @foreach ([
                ['label' => 'All reports', 'value' => $stats['total'] ?? 0],
                ['label' => 'With fine', 'value' => $stats['with_fine'] ?? 0],
                ['label' => 'Without fine', 'value' => $stats['without_fine'] ?? 0],
                ['label' => 'Fine total', 'value' => '₹'.number_format((int) ($stats['fine_amount'] ?? 0))],
            ] as $stat)
                <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                    <p class="mt-1 text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
            <div class="fi-crm-form grid gap-3 sm:grid-cols-2">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Student, staff, or what happened"
                    class="fi-crm-input block min-h-11 w-full sm:col-span-2"
                />
                <x-crm.select wire:model.live="fineFilter" class="min-h-11 w-full">
                    <option value="">All fines</option>
                    <option value="fine">Fine charged</option>
                    <option value="without">Without fine</option>
                </x-crm.select>
                <x-crm.select wire:model.live="typeFilter" class="min-h-11 w-full">
                    <option value="">All types</option>
                    @foreach ($typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-crm.select>
            </div>
        </div>

        @if ($reports === null || $reports->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
                <p class="text-base font-semibold text-gray-950 dark:text-white">No reports match</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try another name, or clear the filters.</p>
            </div>
        @else
            @if (($canApproveParentNotice ?? false) && count($noticeIds ?? []) > 0)
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        wire:click="approveSelectedNotices"
                        wire:confirm="Send the parent WhatsApp for {{ count($noticeIds) }} selected {{ count($noticeIds) === 1 ? 'report' : 'reports' }}?"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-500"
                    >Approve and send</button>
                    <button
                        type="button"
                        wire:click="declineSelectedNotices"
                        wire:confirm="Do not send the parent message for the selected reports? The reports stay saved."
                        class="inline-flex min-h-11 items-center justify-center rounded-xl bg-white px-4 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10"
                    >Do not send</button>
                </div>
            @endif
            <div class="space-y-3">
                @foreach ($reports as $report)
                    <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start gap-3">
                            @if (($canApproveParentNotice ?? false) && $report->parent_notice_status?->value === 'waiting')
                                <input type="checkbox" value="{{ $report->id }}" wire:model.live="noticeIds" class="mt-1 h-4 w-4 rounded border-gray-300 text-rose-600" />
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $report->student_id]) }}" class="truncate text-base font-bold text-gray-950 hover:underline dark:text-white">{{ $report->student?->name ?? 'Student' }}</a>
                                        <p class="mt-1 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $report->typeLabelList() }}</p>
                                    </div>
                                    <span @class([
                                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200' => $report->fine_charged,
                                        'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! $report->fine_charged,
                                    ])>{{ $report->fineSummary() }}</span>
                                </div>
                                <p class="mt-3 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $report->note }}</p>
                                <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    {{ $report->recorded_on?->format('d M Y') }}
                                    · Entered by {{ $report->recordedBy?->name ?: 'Unknown' }}
                                    @if ($report->parentNoticeLabel())
                                        · {{ $report->parentNoticeLabel() }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($reports->hasPages())
                <div class="pt-2">
                    {{ $reports->links() }}
                </div>
            @endif
        @endif
    @else
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ ($reportMode ?? 'one') === 'one' ? 'Report one student' : 'Report many students' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ ($reportMode ?? 'one') === 'one'
                    ? 'Pick the class, then one student. The report is saved on that profile.'
                    : 'Tick the students. The same type, note, and fine are saved on each profile.' }}
            </p>

            @if ($classOptions === [])
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No active class is available for you.</p>
            @else
                <div class="fi-crm-form mt-4 grid gap-4">
                    <div>
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Class</label>
                        <x-crm.select wire:model.live="bulkBatchId" class="mt-2 min-h-11 w-full">
                            <option value="">Select a class</option>
                            @foreach ($classOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </x-crm.select>
                    </div>

                    @if ($bulkBatchId !== '')
                        @if (($reportMode ?? 'one') === 'one')
                            <div>
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Student</label>
                                @if ($bulkStudents->isEmpty())
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No active students in this class.</p>
                                @else
                                    <x-crm.select wire:model.live="singleStudentId" class="mt-2 min-h-11 w-full">
                                        <option value="">Select a student</option>
                                        @foreach ($bulkStudents as $student)
                                            <option value="{{ $student->id }}">{{ $student->name }}</option>
                                        @endforeach
                                    </x-crm.select>
                                @endif
                            </div>
                        @else
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Students</p>
                                @if ($bulkStudents->isNotEmpty())
                                    <button type="button" wire:click="toggleSelectAllStudents" class="text-sm font-semibold text-rose-700 hover:underline dark:text-rose-300">
                                        {{ $allStudentsSelected ? 'Clear all' : 'Select all' }}
                                    </button>
                                @endif
                            </div>

                            @if ($bulkStudents->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">No active students in this class.</p>
                            @else
                                <div class="max-h-72 space-y-2 overflow-y-auto">
                                    @foreach ($bulkStudents as $student)
                                        <label class="flex min-h-11 items-center gap-3 rounded-xl px-3 ring-1 ring-gray-200 dark:ring-white/10">
                                            <input type="checkbox" value="{{ $student->id }}" wire:model.live="selectedStudentIds" class="h-4 w-4 rounded border-gray-300 text-rose-600" />
                                            <span class="min-w-0 truncate text-sm font-medium text-gray-950 dark:text-white">{{ $student->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endif

                    @if ($pickedCount > 0)
                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Date</label>
                            <input type="date" wire:model="bulkRecordedOn" max="{{ now()->toDateString() }}" class="fi-crm-input mt-2 block min-h-11 w-full" />
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Type</label>
                            <x-crm.select wire:model="bulkTypeKey" class="mt-2 min-h-11 w-full">
                                <option value="">Select a type</option>
                                @foreach ($typeOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </x-crm.select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Fine</label>
                                <x-crm.select wire:model.live="bulkFineChoice" class="mt-2 min-h-11 w-full">
                                    <option value="without_fine">Without fine</option>
                                    <option value="fine_charged">Fine charged</option>
                                </x-crm.select>
                            </div>
                            @if ($bulkFineChoice === 'fine_charged')
                                <div>
                                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Amount (₹)</label>
                                    <input type="number" min="1" step="1" inputmode="numeric" wire:model="bulkAmount" class="fi-crm-input mt-2 block min-h-11 w-full" placeholder="e.g. 200" />
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">What happened</label>
                            <textarea wire:model="bulkNote" rows="3" maxlength="1000" class="fi-crm-input mt-2 block min-h-24 w-full" placeholder="Short note about this incident"></textarea>
                        </div>

                        <button
                            type="button"
                            wire:click="{{ $saveAction }}"
                            wire:confirm="Save this report for {{ $confirmWho }}? The parent message waits until it is approved."
                            wire:loading.attr="disabled"
                            wire:target="saveSingleReport,sendBulkReport"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-500 disabled:opacity-70 sm:w-auto"
                        >
                            {{ $saveLabel }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
