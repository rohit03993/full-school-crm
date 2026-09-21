@if (! is_array($markSheet))
    <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Exam not found. Go back to {{ \App\Support\CrmMenuLabels::examResults() }} and choose <strong>View sheet</strong>.
    </div>
@else
    @php
        $status = $resultStatus ?? ['status' => 'none', 'label' => 'Not published'];
        $statusBadge = match ($status['status'] ?? 'none') {
            'published' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300',
            'issued' => 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300',
            'draft' => 'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300',
            default => 'bg-gray-50 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-300',
        };
        $declaration = $status['declaration'] ?? null;
    @endphp

    <div class="mb-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-2 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-5 sm:py-3">
            <div class="flex min-w-0 flex-wrap items-center gap-2">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Results</h3>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold ring-1 {{ $statusBadge }}">{{ $status['label'] }}</span>
            </div>
            @if (($canPublish ?? false) && ! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="flex w-full items-center gap-2">
                    <label for="declaration-date" class="sr-only">Publish date</label>
                    <input id="declaration-date" type="date" wire:model="declarationDate" class="fi-input min-w-0 flex-1 rounded-lg border-gray-300 py-1.5 text-sm sm:max-w-[11rem] dark:border-white/10 dark:bg-white/5" />
                    <button type="button" wire:click="publishResults" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-semibold text-white hover:bg-emerald-500">
                        Publish
                    </button>
                </div>
            @elseif (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'published')
                <div class="flex w-full items-center gap-2">
                    <label for="issue-date" class="sr-only">Issue date</label>
                    <input id="issue-date" type="date" wire:model="marksheetIssueDate" class="fi-input min-w-0 flex-1 rounded-lg border-gray-300 py-1.5 text-sm sm:max-w-[11rem] dark:border-white/10 dark:bg-white/5" />
                    <button type="button" wire:click="issueMarksheets" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white hover:bg-primary-500">
                        PDFs
                    </button>
                </div>
            @endif
        </div>

        @if (($marksAreLocked ?? false) && in_array($status['status'] ?? 'none', ['published', 'issued'], true))
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-sky-100 bg-sky-50/70 px-4 py-2 text-xs text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200 sm:px-5">
                <p>Marks locked — unlock before editing this grid.</p>
                @if ($canManagePublish ?? false)
                    <div class="flex flex-wrap gap-2">
                        <button type="button" wire:click="unlockMarks" wire:confirm="Unlock marks so teachers can edit? Re-publish after corrections." class="rounded-md bg-sky-600 px-2.5 py-1 font-semibold text-white hover:bg-sky-500">
                            Unlock
                        </button>
                        <button type="button" wire:click="unpublishResults" wire:confirm="Unpublish results? Students will no longer see marks online." class="rounded-md bg-red-600 px-2.5 py-1 font-semibold text-white hover:bg-red-500">
                            Unpublish
                        </button>
                    </div>
                @endif
            </div>
        @elseif (in_array($status['status'] ?? 'none', ['published', 'issued'], true) && ($canManagePublish ?? false))
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-100 bg-amber-50/70 px-4 py-2 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200 sm:px-5">
                <p>Marks unlocked. Re-publish after corrections.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="lockMarks" class="rounded-md bg-amber-600 px-2.5 py-1 font-semibold text-white hover:bg-amber-500">
                        Lock
                    </button>
                    <button type="button" wire:click="unpublishResults" wire:confirm="Unpublish results? Students will no longer see marks online." class="rounded-md bg-red-600 px-2.5 py-1 font-semibold text-white hover:bg-red-500">
                        Unpublish
                    </button>
                </div>
            </div>
        @endif

        @if (($examWindowStatus['exists'] ?? false) && ! ($canPublish ?? false) && ! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-100 bg-amber-50/70 px-4 py-2 text-xs text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200 sm:px-5">
                <p>Teachers still entering: {{ $examWindowStatus['label'] ?? 'Pending' }}. Approve before publishing.</p>
                @if (! empty($examWindowStatus['url']))
                    <a href="{{ $examWindowStatus['url'] }}" class="rounded-md bg-amber-600 px-2.5 py-1 font-semibold text-white hover:bg-amber-500">
                        Open exam
                    </a>
                @endif
            </div>
        @endif

        @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
            <div class="border-t border-gray-100 px-4 py-2 text-xs text-gray-600 dark:border-white/10 dark:text-gray-400 sm:px-5">
                Declaration date: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Support\StudentExamMarksMatrix::formatDateLabel($declaration?->declaration_date ?? null) }}</strong>
                @if (filled($declaration?->marksheet_issue_date))
                    · Issue date: <strong class="text-gray-800 dark:text-gray-200">{{ \App\Support\StudentExamMarksMatrix::formatDateLabel($declaration->marksheet_issue_date) }}</strong>
                @endif
                @if (filled($declaration?->remarks))
                    · Remarks: {{ $declaration->remarks }}
                @endif
            </div>
        @endif

        @if (! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
            <details class="border-t border-gray-100 dark:border-white/10">
                <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5 sm:px-5">
                    Principal remarks (optional)
                </summary>
                <div class="px-4 pb-3 sm:px-5">
                    <textarea wire:model="principalRemarks" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" placeholder="e.g. Keep up the good work."></textarea>
                    <button type="button" wire:click="savePrincipalRemarks" class="mt-2 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 dark:bg-gray-700">
                        Save remarks
                    </button>
                </div>
            </details>
        @endif

        @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
            <div class="flex flex-wrap items-end gap-2 border-t border-gray-100 px-4 py-3 dark:border-white/10 sm:px-5">
                <div class="min-w-[10rem] flex-1">
                    <x-crm.field label="Issue date" for="regenerate-issue-date">
                        <input id="regenerate-issue-date" type="date" wire:model="marksheetIssueDate" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                    </x-crm.field>
                </div>
                <button type="button" wire:click="regenerateMarksheets" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500">
                    Regenerate PDFs
                </button>
            </div>
        @endif
    </div>

    @php
        $editing = (bool) ($editingMarks ?? false) && (bool) ($canBulkEditMarks ?? false);
        $sheetRows = is_array($markSheet['rows'] ?? null) ? $markSheet['rows'] : [];
        $sheetSubjects = is_array($markSheet['subjects'] ?? null) ? $markSheet['subjects'] : [];
        $examDateLabel = \App\Support\StudentExamMarksMatrix::formatDateLabel($markSheet['date'] ?? null);
    @endphp

    <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">
        <p>
            {{ $markSheet['batch'] ?? '—' }}
            @if ($examDateLabel !== '—')
                · {{ $examDateLabel }}
            @endif
            @if ($marksAreLocked ?? false)
                · Marks locked
            @endif
        </p>
    </div>

    <div @class(['mb-4 space-y-3 lg:hidden', 'crm-sticky-above-nav-pad' => $editing])>
        @foreach ($sheetRows as $row)
            @continue(! is_array($row))
            <div class="rounded-xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $row['student_name'] ?? '—' }}</p>
                        <p class="font-mono text-xs text-gray-500">{{ $row['roll_number'] ?? '—' }}</p>
                    </div>
                    @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                        @php
                            $sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null;
                        @endphp
                        <div class="shrink-0 text-right">
                            <p class="text-[10px] font-semibold uppercase text-gray-500">Rank</p>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $sheet?->rank ?? '—' }}</p>
                        </div>
                    @endif
                </div>
                <div class="mt-2 divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($sheetSubjects as $subject)
                        @php
                            $cell = \App\Support\StudentExamMarksMatrix::sheetCell(is_array($row) ? $row : [], (string) $subject);
                            $display = $cell['display'];
                        @endphp
                        <div class="flex items-center justify-between gap-3 py-2">
                            <p class="min-w-0 truncate text-xs font-medium text-gray-500">{{ $subject }}</p>
                            <div class="shrink-0 text-right tabular-nums text-sm font-semibold {{ ! $editing && $display === 'Absent' ? 'text-gray-400' : 'text-gray-800 dark:text-gray-200' }}">
                                @if ($editing)
                                    <input
                                        type="number"
                                        step="0.01"
                                        inputmode="decimal"
                                        @if (($cell['max'] ?? null) !== null) min="{{ \App\Support\StudentExamMarksMatrix::obtainedFloor((float) $cell['max']) }}" max="{{ $cell['max'] }}" @endif
                                        wire:model="marksDraft.{{ $row['student_id'] }}.{{ $subject }}"
                                        class="w-[5.5rem] rounded-md border border-gray-200 bg-white px-2 py-1.5 text-right text-sm font-semibold dark:border-white/10 dark:bg-gray-950"
                                        placeholder="{{ ($cell['max'] ?? null) !== null ? \App\Support\StudentExamMarksMatrix::formatMaxLabel($cell['max']) : 'Marks' }}"
                                    >
                                @else
                                    {{ $display }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                    @php
                        $sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null;
                    @endphp
                    <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                        @if ($sheet?->hasPdf())
                            <a href="{{ route('admin.marksheets.preview', $sheet) }}" target="_blank" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-600 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-400">
                                View PDF marksheet
                            </a>
                        @else
                            <span class="text-xs text-gray-400">No marksheet PDF</span>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="hidden overflow-x-auto rounded-xl bg-white ring-1 ring-gray-200 lg:block dark:bg-gray-900 dark:ring-white/10">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Roll No.</th>
                    <th class="px-4 py-2.5">Student</th>
                    @foreach ($sheetSubjects as $subject)
                        <th class="px-4 py-2.5 text-center">{{ $subject }}</th>
                    @endforeach
                    @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                        <th class="px-4 py-2.5 text-center">Rank</th>
                    @endif
                    @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                        <th class="px-4 py-2.5 text-right">Marksheet</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($sheetRows as $row)
                    @continue(! is_array($row))
                    <tr class="group bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-white/5">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-mono text-xs text-gray-600 group-hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-300 dark:group-hover:bg-white/5">
                            {{ $row['roll_number'] ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row['student_name'] ?? '—' }}</td>
                        @foreach ($sheetSubjects as $subject)
                            @php
                                $cell = \App\Support\StudentExamMarksMatrix::sheetCell(is_array($row) ? $row : [], (string) $subject);
                                $display = $cell['display'];
                            @endphp
                            <td class="px-2 py-1.5 text-center tabular-nums {{ ! $editing && $display === 'Absent' ? 'text-gray-400' : 'text-gray-800 dark:text-gray-200' }}">
                                @if ($editing)
                                    <input
                                        type="number"
                                        step="0.01"
                                        @if (($cell['max'] ?? null) !== null) min="{{ \App\Support\StudentExamMarksMatrix::obtainedFloor((float) $cell['max']) }}" max="{{ $cell['max'] }}" @endif
                                        wire:model="marksDraft.{{ $row['student_id'] }}.{{ $subject }}"
                                        class="mx-auto w-[4.5rem] rounded-md border border-gray-200 bg-white px-1.5 py-1 text-center text-sm dark:border-white/10 dark:bg-gray-950"
                                        placeholder="{{ ($cell['max'] ?? null) !== null ? '/ '.\App\Support\StudentExamMarksMatrix::formatMaxLabel($cell['max']) : '' }}"
                                    >
                                @else
                                    {{ $display }}
                                @endif
                            </td>
                        @endforeach
                        @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                            @php
                                $sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null;
                            @endphp
                            <td class="px-4 py-2.5 text-center font-semibold text-gray-800 dark:text-gray-200">
                                {{ $sheet?->rank ?? '—' }}
                            </td>
                        @endif
                        @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                            @php
                                $sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null;
                            @endphp
                            <td class="px-4 py-2.5 text-right">
                                @if ($sheet?->hasPdf())
                                    <a href="{{ route('admin.marksheets.preview', $sheet) }}" target="_blank" class="text-xs font-semibold text-primary-600 hover:underline">View PDF</a>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($editing)
        <div class="crm-sticky-above-nav mt-3 flex flex-col gap-2 rounded-xl border border-primary-200 bg-white p-3 shadow-lg sm:flex-row sm:items-center dark:border-primary-500/30 dark:bg-gray-900">
            <button type="button" wire:click="saveBulkMarks" wire:loading.attr="disabled" wire:target="saveBulkMarks" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70 sm:min-h-0 sm:w-auto sm:py-1.5 sm:text-xs">
                <span wire:loading.remove wire:target="saveBulkMarks">Save marks</span>
                <span wire:loading wire:target="saveBulkMarks">Saving…</span>
            </button>
            <button type="button" wire:click="cancelBulkEdit" wire:loading.attr="disabled" wire:target="saveBulkMarks" class="inline-flex min-h-10 w-full items-center justify-center rounded-lg border border-gray-200 px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:min-h-0 sm:w-auto sm:py-1.5 sm:text-xs dark:border-white/10 dark:text-gray-300">
                Cancel
            </button>
            <p class="text-center text-xs text-gray-500 sm:text-left dark:text-gray-400">Empty = Absent</p>
        </div>
    @endif

    @if (! empty($auditTrailEntries))
        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Result audit trail</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Publish, lock, import, and post-publish mark changes for this exam.</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($auditTrailEntries as $entry)
                    <div class="px-4 py-3 sm:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ \App\Support\ResultAuditTrail::labelForAction($entry->action) }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry->created_at?->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            {{ $entry->user_name }}
                            @if (filled($entry->detail))
                                · {{ $entry->detail }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('filament.pages.partials.exam-marks-whatsapp-send', [
        'canSendWhatsApp' => $canSendWhatsApp ?? false,
        'defaultMarksTemplateName' => $defaultMarksTemplateName ?? null,
        'examMarksAutomationsUrl' => $examMarksAutomationsUrl ?? null,
        'whatsappTemplateOptions' => $whatsappTemplateOptions ?? [],
        'whatsappTemplateInputId' => 'wa-template-review',
    ])
@endif
