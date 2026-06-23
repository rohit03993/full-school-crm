@if (! is_array($markSheet))
    <div class="rounded-xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Test not found. Go back to Tests &amp; Exams and choose <strong>View sheet</strong>.
    </div>
@else
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
                    Queue a pre-approved template to every student with marks on this sheet. Map variables to
                    <code class="text-xs">student.name</code>,
                    <code class="text-xs">activity.test_name</code>,
                    <code class="text-xs">activity.marks_summary</code>, etc.
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
