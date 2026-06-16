<div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="px-4 py-2.5">Activity</th>
                <th class="px-4 py-2.5">Batch</th>
                <th class="px-4 py-2.5">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($records as $record)
                @php $activity = $record->attendable; @endphp
                <tr class="bg-white dark:bg-gray-900">
                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                        {{ $activity?->displayTitle() ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $activity?->batch?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                        @if ($activity instanceof \App\Models\PracticalSession)
                            {{ $activity->session_date->format('d M Y') }}
                        @elseif ($activity instanceof \App\Models\IndustrialVisit)
                            {{ $activity->visit_date->format('d M Y') }}
                        @elseif ($activity instanceof \App\Models\Seminar)
                            {{ $activity->seminar_date->format('d M Y') }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
