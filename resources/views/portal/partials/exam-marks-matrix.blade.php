<div class="overflow-x-auto scrollbar-none">
    <table class="w-full min-w-[28rem] text-left text-sm">
        <thead class="bg-navy-50 text-[10px] font-bold uppercase tracking-wide text-navy-500">
            <tr>
                <th class="sticky left-0 z-10 bg-navy-50 px-3 py-2.5 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.08)]">Test / Exam</th>
                <th class="px-3 py-2.5">Date</th>
                <th class="px-3 py-2.5">Batch</th>
                @foreach ($matrix['subjects'] as $subject)
                    <th class="px-3 py-2.5 text-center">{{ $subject }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-navy-100">
            @foreach ($matrix['rows'] as $row)
                <tr>
                    <td class="sticky left-0 z-10 bg-white px-3 py-2.5 font-medium text-navy-900 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.06)]">
                        {{ $row['label'] }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2.5 text-navy-600">
                        {{ $row['date']?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-3 py-2.5 text-navy-600">{{ $row['batch'] ?? '—' }}</td>
                    @foreach ($matrix['subjects'] as $subject)
                        <td class="px-3 py-2.5 text-center font-medium text-navy-800">
                            {{ $row['scores'][$subject]['display'] ?? '—' }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
