{{-- Mobile / tablet: one card per exam. Desktop: wide matrix table. --}}
@php
    $subjectsFor = function (array $row) use ($matrix): array {
        return $row['exam_subjects'] ?? ($matrix['subjects'] ?? []);
    };
@endphp
<div class="space-y-2 lg:hidden">
    <p class="text-xs text-gray-500 dark:text-gray-400">Class exams — marks if this student appeared, blank if not.</p>
    @foreach ($matrix['rows'] as $row)
        @php $examSubjects = $subjectsFor($row); @endphp
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
                                @if (($cell['marks'] ?? null) !== null)
                                    {{ $cell['display'] }}
                                @elseif (($cell['max'] ?? null) !== null)
                                    <span class="font-normal text-gray-400">/ {{ rtrim(rtrim(number_format((float) $cell['max'], 2), '0'), '.') }}</span>
                                @else
                                    <span class="font-normal text-gray-400">&nbsp;</span>
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
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
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($matrix['rows'] as $row)
                @php $examSubjects = $subjectsFor($row); @endphp
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
                            @elseif (($cell['marks'] ?? null) !== null)
                                {{ $cell['display'] }}
                            @elseif (($cell['max'] ?? null) !== null)
                                <span class="text-gray-400">/ {{ rtrim(rtrim(number_format((float) $cell['max'], 2), '0'), '.') }}</span>
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
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
