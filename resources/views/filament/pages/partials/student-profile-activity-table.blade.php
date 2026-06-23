<div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
    <table class="w-full text-left text-sm">
        <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="px-4 py-2.5">Activity</th>
                <th class="px-4 py-2.5">Batch</th>
                <th class="px-4 py-2.5">Date</th>
                <th class="px-4 py-2.5">{{ $attendanceOnly ? 'Status' : 'Marks' }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($records as $record)
                @php
                    $activity = $record->attendable;
                    $maxMarks = $activity?->metadataValue('max_marks');
                    $marksLabel = $record->marks_obtained !== null
                        ? rtrim(rtrim(number_format((float) $record->marks_obtained, 2), '0'), '.').($maxMarks ? ' / '.$maxMarks : '')
                        : ($record->grade ?: '—');
                @endphp
                <tr class="bg-white dark:bg-gray-900">
                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                        {{ $activity?->title ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $activity?->batch?->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                        {{ $activity?->session_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">
                        @if ($attendanceOnly)
                            <span @class([
                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $record->is_present,
                                'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => ! $record->is_present,
                            ])>
                                {{ $record->is_present ? 'Present' : 'Absent' }}
                            </span>
                        @else
                            {{ $marksLabel }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
