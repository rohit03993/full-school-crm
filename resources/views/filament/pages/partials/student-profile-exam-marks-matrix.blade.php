{{-- Mobile / tablet: one card per exam. Desktop: wide matrix table. --}}
@php
    $subjectsFor = function (array $row) use ($matrix): array {
        return $row['exam_subjects'] ?? ($matrix['subjects'] ?? []);
    };
    $isEditing = function (array $row) use ($examMarksEditGroupKey): bool {
        return ($examMarksEditGroupKey ?? null) !== null
            && ($examMarksEditGroupKey ?? null) === ($row['group_key'] ?? null);
    };
    $formatMax = function ($max): string {
        if ($max === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $max, 2), '0'), '.');
    };
    $examMarksWhatsAppSends = $examMarksWhatsAppSends ?? [];
    $whatsAppLabel = function (array $row) use ($examMarksWhatsAppSends): string {
        $prior = $examMarksWhatsAppSends[$row['group_key'] ?? ''] ?? null;

        return (($prior['status'] ?? null) === 'sent') ? 'Resend' : 'WhatsApp';
    };
@endphp
<div class="space-y-2 lg:hidden">
    <p class="text-xs text-gray-500 dark:text-gray-400">Class exams — marks if this student appeared, blank if not. Add or edit this student only; the class sheet stays as it is.</p>
    @foreach ($matrix['rows'] as $row)
        @php
            $examSubjects = $subjectsFor($row);
            $editing = $isEditing($row);
        @endphp
        <div class="rounded-xl bg-white p-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ $row['date']?->format('d M Y') ?? '—' }}
                        @if ($row['batch'] ?? null)
                            · {{ $row['batch'] }}
                        @endif
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total</p>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">
                        {{ $row['total']['display'] ?? '—' }}
                    </p>
                </div>
            </div>

            @if ($examSubjects !== [])
                <dl class="mt-3 grid grid-cols-2 gap-2 border-t border-gray-100 pt-3 dark:border-white/10">
                    @foreach ($examSubjects as $subject)
                        @php $cell = $row['scores'][$subject] ?? ['display' => '', 'max' => null, 'marks' => null]; @endphp
                        <div class="rounded-lg bg-gray-50 px-2.5 py-2 dark:bg-white/5">
                            <dt class="truncate text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $subject }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                @if ($editing)
                                    <input
                                        type="number"
                                        step="0.01"
                                        @if (($cell['max'] ?? null) !== null) min="{{ \App\Support\StudentExamMarksMatrix::obtainedFloor((float) $cell['max']) }}" max="{{ $cell['max'] }}" @endif
                                        wire:model="examMarksDraft.{{ $subject }}"
                                        class="w-full rounded-md border border-gray-200 bg-white px-2 py-1 text-sm dark:border-white/10 dark:bg-gray-950"
                                        placeholder="{{ ($cell['max'] ?? null) !== null ? '/ '.$formatMax($cell['max']) : 'Marks' }}"
                                    >
                                @elseif (($cell['marks'] ?? null) !== null)
                                    {{ $cell['display'] }}
                                @elseif (($cell['max'] ?? null) !== null)
                                    <span class="font-normal text-gray-400">/ {{ $formatMax($cell['max']) }}</span>
                                @else
                                    <span class="font-normal text-gray-400">&nbsp;</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if (($canEditExamMarks ?? false) || (($canSendExamMarksWhatsApp ?? false) && ($row['appeared'] ?? false)))
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($canEditExamMarks ?? false)
                        @if ($row['marks_locked'] ?? false)
                            <p class="text-xs text-amber-700 dark:text-amber-300">Locked on the class sheet — unlock there before editing.</p>
                        @elseif ($editing)
                            <button type="button" wire:click="saveExamMarks" wire:loading.attr="disabled" wire:target="saveExamMarks" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70">
                                <span wire:loading.remove wire:target="saveExamMarks">Save marks</span>
                                <span wire:loading wire:target="saveExamMarks">Saving…</span>
                            </button>
                            <button type="button" wire:click="cancelExamMarksEdit" wire:loading.attr="disabled" wire:target="saveExamMarks" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300">
                                Cancel
                            </button>
                        @else
                            <button type="button" wire:click="startExamMarksEdit({{ \Illuminate\Support\Js::from($row['group_key']) }})" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-100">
                                {{ ($row['appeared'] ?? false) ? 'Edit marks' : 'Add marks' }}
                            </button>
                        @endif
                    @endif
                    @if (($canSendExamMarksWhatsApp ?? false) && ($row['appeared'] ?? false) && ! $editing)
                        <button
                            type="button"
                            wire:click="confirmSendExamMarksWhatsApp({{ \Illuminate\Support\Js::from($row['group_key']) }})"
                            wire:loading.attr="disabled"
                            wire:target="confirmSendExamMarksWhatsApp"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 disabled:cursor-wait disabled:opacity-70"
                        >
                            {{ $whatsAppLabel($row) }}
                        </button>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
</div>

<div class="hidden overflow-x-auto rounded-xl ring-1 ring-gray-200 lg:block dark:ring-white/10">
    <table class="w-full min-w-[32rem] text-left text-sm">
        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2.5 dark:bg-gray-900">Test / Exam</th>
                <th class="px-4 py-2.5">Date</th>
                <th class="px-4 py-2.5">Batch</th>
                @foreach ($matrix['subjects'] as $subject)
                    <th class="px-4 py-2.5 text-center">{{ $subject }}</th>
                @endforeach
                <th class="px-4 py-2.5 text-center">Total</th>
                <th class="px-4 py-2.5 text-center">%</th>
                @if (($canEditExamMarks ?? false) || ($canSendExamMarksWhatsApp ?? false))
                    <th class="px-4 py-2.5"></th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($matrix['rows'] as $row)
                @php
                    $examSubjects = $subjectsFor($row);
                    $editing = $isEditing($row);
                @endphp
                <tr class="bg-white dark:bg-gray-900">
                    <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-medium text-gray-950 dark:bg-gray-900 dark:text-white">
                        {{ $row['label'] }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-400">
                        {{ $row['date']?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['batch'] ?? '—' }}</td>
                    @foreach ($matrix['subjects'] as $subject)
                        @php
                            $onPaper = in_array($subject, $examSubjects, true);
                            $cell = $row['scores'][$subject] ?? null;
                        @endphp
                        <td class="px-4 py-2.5 text-center font-medium text-gray-800 dark:text-gray-200">
                            @if (! $onPaper)
                                <span class="text-gray-300">—</span>
                            @elseif ($editing)
                                <input
                                    type="number"
                                    step="0.01"
                                    @if (($cell['max'] ?? null) !== null) min="{{ \App\Support\StudentExamMarksMatrix::obtainedFloor((float) $cell['max']) }}" max="{{ $cell['max'] }}" @endif
                                    wire:model="examMarksDraft.{{ $subject }}"
                                    class="mx-auto w-20 rounded-md border border-gray-200 bg-white px-2 py-1 text-center text-sm dark:border-white/10 dark:bg-gray-950"
                                    placeholder="{{ ($cell['max'] ?? null) !== null ? '/ '.$formatMax($cell['max']) : '' }}"
                                >
                            @elseif (($cell['marks'] ?? null) !== null)
                                {{ $cell['display'] }}
                            @elseif (($cell['max'] ?? null) !== null)
                                <span class="text-gray-400">/ {{ $formatMax($cell['max']) }}</span>
                            @else
                                <span class="text-gray-400">&nbsp;</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-2.5 text-center font-semibold text-gray-950 dark:text-white">
                        {{ $row['total']['display'] ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-center font-semibold text-primary-700 dark:text-primary-300">
                        @if (($row['total']['percentage'] ?? null) !== null)
                            {{ rtrim(rtrim(number_format((float) $row['total']['percentage'], 2), '0'), '.') }}%
                        @else
                            —
                        @endif
                    </td>
                    @if (($canEditExamMarks ?? false) || ($canSendExamMarksWhatsApp ?? false))
                        <td class="whitespace-nowrap px-4 py-2.5">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                @if ($canEditExamMarks ?? false)
                                    @if ($row['marks_locked'] ?? false)
                                        <span class="text-xs text-amber-700 dark:text-amber-300">Locked</span>
                                    @elseif ($editing)
                                        <button type="button" wire:click="saveExamMarks" wire:loading.attr="disabled" wire:target="saveExamMarks" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:cursor-wait disabled:opacity-70">
                                            <span wire:loading.remove wire:target="saveExamMarks">Save</span>
                                            <span wire:loading wire:target="saveExamMarks">Saving…</span>
                                        </button>
                                        <button type="button" wire:click="cancelExamMarksEdit" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300">Cancel</button>
                                    @else
                                        <button type="button" wire:click="startExamMarksEdit({{ \Illuminate\Support\Js::from($row['group_key']) }})" class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-800 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-100">
                                            {{ ($row['appeared'] ?? false) ? 'Edit marks' : 'Add marks' }}
                                        </button>
                                    @endif
                                @endif
                                @if (($canSendExamMarksWhatsApp ?? false) && ($row['appeared'] ?? false) && ! $editing)
                                    <button
                                        type="button"
                                        wire:click="confirmSendExamMarksWhatsApp({{ \Illuminate\Support\Js::from($row['group_key']) }})"
                                        wire:loading.attr="disabled"
                                        wire:target="confirmSendExamMarksWhatsApp"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 disabled:cursor-wait disabled:opacity-70"
                                    >
                                        {{ $whatsAppLabel($row) }}
                                    </button>
                                @endif
                            </div>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
