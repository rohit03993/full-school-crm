<div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
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
                <tr class="bg-white dark:bg-gray-900">
                    <td class="sticky left-0 z-10 bg-white px-4 py-2.5 font-medium text-gray-950 dark:bg-gray-900 dark:text-white">
                        {{ $row['label'] }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-400">
                        {{ $row['date']?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $row['batch'] ?? '—' }}</td>
                    @foreach ($matrix['subjects'] as $subject)
                        <td class="px-4 py-2.5 text-center font-medium text-gray-800 dark:text-gray-200">
                            {{ $row['scores'][$subject]['display'] ?? '—' }}
                        </td>
                    @endforeach
                    <td class="px-4 py-2.5 text-center font-semibold text-gray-950 dark:text-white">
                        @if (($row['total']['max'] ?? null) !== null)
                            {{ rtrim(rtrim(number_format((float) ($row['total']['marks'] ?? 0), 2), '0'), '.') }}
                            /
                            {{ rtrim(rtrim(number_format((float) $row['total']['max'], 2), '0'), '.') }}
                        @else
                            {{ $row['total']['display'] ?? '—' }}
                        @endif
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
