@if (! is_array($markSheet))
    <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Test not found. Go back to {{ \App\Support\CrmMenuLabels::examResults() }} and choose <strong>View sheet</strong>.
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

    <div class="mb-5 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-gray-950 dark:text-white">Result declaration</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Publish marks to the student portal, then issue printable PDF marksheets.</p>
                </div>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $statusBadge }}">{{ $status['label'] }}</span>
            </div>
        </div>
        <div class="grid gap-4 p-4 sm:grid-cols-2 sm:p-6">
            @if (($marksAreLocked ?? false) && in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-500/20 dark:bg-sky-500/5 sm:col-span-2">
                    <p class="text-sm font-semibold text-sky-900 dark:text-sky-200">Marks locked</p>
                    <p class="mt-1 text-xs text-sky-800 dark:text-sky-300">
                        Marks were frozen when results were published. Teachers cannot re-import or edit until Super Admin unlocks.
                    </p>
                    @if ($canManagePublish ?? false)
                        <button type="button" wire:click="unlockMarks" wire:confirm="Unlock marks so teachers can edit? Re-publish after corrections." class="mt-3 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                            Unlock marks (Super Admin)
                        </button>
                    @endif
                </div>
            @elseif (in_array($status['status'] ?? 'none', ['published', 'issued'], true) && ($canManagePublish ?? false))
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5 sm:col-span-2">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Marks unlocked</p>
                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">Edits are allowed. Re-publish after corrections to refresh portal snapshots and ranks.</p>
                    <button type="button" wire:click="lockMarks" class="mt-3 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                        Lock marks again
                    </button>
                </div>
            @endif

            @if (! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10 sm:col-span-2">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Principal remarks (optional)</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Printed on PDF marksheets when enabled in Institute Settings.</p>
                    <textarea wire:model="principalRemarks" rows="3" class="mt-3 block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" placeholder="e.g. Keep up the good work."></textarea>
                    <button type="button" wire:click="savePrincipalRemarks" class="mt-2 rounded-lg bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 dark:bg-gray-700">
                        Save remarks
                    </button>
                </div>
            @endif

            @if (($examWindowStatus['exists'] ?? false) && ! ($canPublish ?? false) && ! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-500/20 dark:bg-amber-500/5 sm:col-span-2">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Exam window: {{ $examWindowStatus['label'] ?? 'Pending' }}</p>
                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                        Approve the exam window before publishing. Class lead submits → admin approves on the exam window page.
                    </p>
                    @if (! empty($examWindowStatus['url']))
                        <a href="{{ $examWindowStatus['url'] }}" class="mt-3 inline-flex rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                            Open exam window
                        </a>
                    @endif
                </div>
            @endif
            @if (($canPublish ?? false) && ! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">1. Publish online</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Students will see marks in the portal. Marks auto-lock on publish.</p>
                    <div class="mt-3">
                        <x-crm.field label="Declaration date" for="declaration-date">
                            <input id="declaration-date" type="date" wire:model="declarationDate" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                        </x-crm.field>
                    </div>
                    <button type="button" wire:click="publishResults" class="mt-3 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-500">
                        Publish results
                    </button>
                </div>
            @elseif (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/5 sm:col-span-2">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">Published to student portal</p>
                    <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-300">
                        Declaration date:
                        <strong>{{ $declaration?->declaration_date?->format('d M Y') ?? '—' }}</strong>
                        @if ($declaration?->marksheet_issue_date)
                            · Marksheet issue date: <strong>{{ $declaration->marksheet_issue_date->format('d M Y') }}</strong>
                        @endif
                    </p>
                    @if (filled($declaration?->remarks))
                        <p class="mt-2 text-xs text-gray-700 dark:text-gray-300"><strong>Principal remarks:</strong> {{ $declaration->remarks }}</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Students see marks online. Collect printed PDF marksheets from the office.</p>

                    @if (($canManagePublish ?? false))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @if ($marksAreLocked ?? false)
                                <button type="button" wire:click="unlockMarks" wire:confirm="Unlock marks so teachers can edit? Re-publish after corrections." class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">
                                    Unlock marks
                                </button>
                            @else
                                <button type="button" wire:click="lockMarks" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500">
                                    Lock marks
                                </button>
                            @endif
                            <button type="button" wire:click="unpublishResults" wire:confirm="Unpublish results? Students will no longer see marks online." class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-500">
                                Unpublish results
                            </button>
                        </div>
                    @endif

                    @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                        <div class="mt-4 rounded-xl border border-gray-200 bg-white/80 p-4 dark:border-white/10 dark:bg-gray-900/40">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Regenerate PDF marksheets</p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Rebuild PDF files from frozen snapshots (does not read live marks unless unlocked and re-published).</p>
                            <div class="mt-3 max-w-xs">
                                <x-crm.field label="Issue date" for="regenerate-issue-date">
                                    <input id="regenerate-issue-date" type="date" wire:model="marksheetIssueDate" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                                </x-crm.field>
                            </div>
                            <button type="button" wire:click="regenerateMarksheets" class="mt-3 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                                Regenerate PDF marksheets
                            </button>
                        </div>
                    @endif
                </div>
            @endif
            @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'published')
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">2. Issue marksheets (PDF)</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Super Admin only — generates printable marksheets with rank, attendance, and remarks per Institute Settings.</p>
                    <div class="mt-3">
                        <x-crm.field label="Issue date" for="issue-date">
                            <input id="issue-date" type="date" wire:model="marksheetIssueDate" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
                        </x-crm.field>
                    </div>
                    <button type="button" wire:click="issueMarksheets" class="mt-3 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                        Generate PDF marksheets
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ $markSheet['batch'] ?? '—' }} · {{ $markSheet['date']?->format('d M Y') ?? '—' }}
        @if ($marksAreLocked ?? false)
            · <strong class="text-sky-700 dark:text-sky-300">Marks locked</strong> — upload and manual entry disabled
        @else
            · Use <strong>Upload marks</strong> or mark entry to change scores
        @endif
    </div>

    <div class="mb-4 space-y-2 md:hidden">
        @foreach ($markSheet['rows'] as $row)
            <div class="rounded-xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-950 dark:text-white">{{ $row['student_name'] }}</p>
                        <p class="font-mono text-xs text-gray-500">{{ $row['roll_number'] }}</p>
                    </div>
                    @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                        @php($sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null)
                        <div class="text-right">
                            <p class="text-[10px] font-semibold uppercase text-gray-500">Rank</p>
                            <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $sheet?->rank ?? '—' }}</p>
                        </div>
                    @endif
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                    @foreach ($markSheet['subjects'] as $subject)
                        <div class="rounded-lg bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                            <dt class="truncate text-[10px] font-semibold uppercase text-gray-500">{{ $subject }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $row['scores'][$subject] ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                    @php($sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null)
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

    <div class="hidden overflow-x-auto rounded-xl ring-1 ring-gray-200 md:block dark:ring-white/10">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Roll No.</th>
                    <th class="px-4 py-2.5">Student</th>
                    @foreach ($markSheet['subjects'] as $subject)
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
                @foreach ($markSheet['rows'] as $row)
                    <tr class="bg-white dark:bg-gray-900">
                        <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-mono text-gray-950 dark:bg-gray-900 dark:text-white">
                            {{ $row['roll_number'] }}
                        </td>
                        <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row['student_name'] }}</td>
                        @foreach ($markSheet['subjects'] as $subject)
                            <td class="px-4 py-2.5 text-center text-gray-800 dark:text-gray-200">
                                {{ $row['scores'][$subject] ?? '—' }}
                            </td>
                        @endforeach
                        @if (in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                            @php($sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null)
                            <td class="px-4 py-2.5 text-center font-semibold text-gray-800 dark:text-gray-200">
                                {{ $sheet?->rank ?? '—' }}
                            </td>
                        @endif
                        @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'issued')
                            @php($sheet = $studentMarksheets[$row['student_id'] ?? 0] ?? null)
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
                            {{ $entry->user_name ?? 'System' }}
                            @if ($entry->action === 'marks_changed_after_publish')
                                · marks updated after publish
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($canSendWhatsApp ?? false)
        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Send marks via WhatsApp</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Queue a pre-approved template to every student with marks on this sheet.
                </p>
            </div>

            <div class="grid gap-4 p-4 sm:p-6">
                <x-crm.select-input label="WhatsApp template" for="wa-template-review" wire:model="whatsappTemplateId">
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
    @endif
@endif
