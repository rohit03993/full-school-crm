<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <h2 class="text-base font-bold text-gray-950 dark:text-white">Generate consolidated report cards</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Select a batch and at least <strong>two published exams</strong>. Each student gets one PDF with all selected exams.
            </p>
        </div>

        <div class="grid gap-4 p-4 sm:p-6">
            <x-crm.select-input label="Batch / section" for="consolidated-batch" wire:model.live="batchId">
                <option value="">Select batch…</option>
                @foreach ($batchOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-crm.select-input>

            @if ($batchId)
                <div>
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Published exams</p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Tick the exams to include on the consolidated report card.</p>
                    <div class="mt-3 space-y-2">
                        @forelse ($publishedExamOptions as $exam)
                            <label class="flex items-start gap-3 rounded-xl border border-gray-200 px-3 py-2.5 dark:border-white/10">
                                <input type="checkbox" wire:model="selectedGroupKeys" value="{{ $exam['group_key'] }}" class="mt-1 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20 dark:bg-white/5" />
                                <span>
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $exam['label'] }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $exam['date'] }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">No published exams for this batch. Publish results first from {{ \App\Support\CrmMenuLabels::examResults() }}.</p>
                        @endforelse
                    </div>
                </div>
            @endif

            <x-crm.field label="Report issue date" for="consolidated-issue-date">
                <input id="consolidated-issue-date" type="date" wire:model="issueDate" class="fi-input block w-full max-w-xs rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" />
            </x-crm.field>

            <button type="button" wire:click="generateReports" class="justify-self-start rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                Generate consolidated PDFs
            </button>
        </div>
    </div>

    @if (! empty($generatedReports))
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-bold text-gray-950 dark:text-white">Download report cards</h2>
            </div>
            <x-crm.responsive-table>
                <table class="w-full min-w-[28rem] text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Roll</th>
                            <th class="px-4 py-2.5">Student</th>
                            <th class="px-4 py-2.5 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($generatedReports as $report)
                            <tr>
                                <td class="px-4 py-2.5 font-mono" data-label="Roll">{{ $report['roll_number'] }}</td>
                                <td class="crm-responsive-table__title px-4 py-2.5 font-medium text-gray-950 dark:text-white" data-label="">{{ $report['student_name'] }}</td>
                                <td class="crm-responsive-table__actions px-4 py-2.5 text-right" data-label="">
                                    <a href="{{ route('admin.marksheets.consolidated.download', ['path' => $report['pdf_path']]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-600 ring-1 ring-primary-200 hover:underline dark:bg-primary-500/10 dark:text-primary-400">
                                        Download PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        </div>
    @endif

    <p class="text-sm text-gray-500 dark:text-gray-400">
        <a href="{{ $examResultsUrl }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">← Back to {{ \App\Support\CrmMenuLabels::examResults() }}</a>
    </p>
</div>
