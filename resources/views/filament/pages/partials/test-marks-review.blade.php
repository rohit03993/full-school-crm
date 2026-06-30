@if (! is_array($markSheet))
    <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Test not found. Go back to Tests &amp; Exams and choose <strong>View sheet</strong>.
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
            @if (($canPublish ?? false) && ! in_array($status['status'] ?? 'none', ['published', 'issued'], true))
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">1. Publish online</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Students will see marks in the portal after this step.</p>
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
                @php($declaration = $status['declaration'] ?? null)
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/5 sm:col-span-2">
                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-200">Published to student portal</p>
                    <p class="mt-1 text-xs text-emerald-800 dark:text-emerald-300">
                        Declaration date:
                        <strong>{{ $declaration?->declaration_date?->format('d M Y') ?? '—' }}</strong>
                        @if ($declaration?->marksheet_issue_date)
                            · Marksheet issue date: <strong>{{ $declaration->marksheet_issue_date->format('d M Y') }}</strong>
                        @endif
                    </p>
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-400">Students see marks online. Collect printed PDF marksheets from the office.</p>
                </div>
            @endif
            @if (($canIssueMarksheet ?? false) && ($status['status'] ?? '') === 'published')
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">2. Issue marksheets (PDF)</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Super Admin only — generates printable marksheets for every student on this sheet.</p>
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
        · All subjects uploaded together — use <strong>Import / update marks</strong> to change scores.
    </div>

    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Roll No.</th>
                    <th class="px-4 py-2.5">Student</th>
                    @foreach ($markSheet['subjects'] as $subject)
                        <th class="px-4 py-2.5 text-center">{{ $subject }}</th>
                    @endforeach
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
