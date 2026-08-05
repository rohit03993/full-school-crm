<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Staff ID</th>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Designation</th>
                    <th class="px-4 py-3">Mobile</th>
                    <th class="px-4 py-3">IN</th>
                    <th class="px-4 py-3">OUT</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Source</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row['employee_code'] }}</td>
                        <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['designation'] ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row['mobile'] ?: '—' }}</td>
                        <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300">{{ $row['checked_in_at'] ?: '—' }}</td>
                        <td class="px-4 py-3 text-rose-700 dark:text-rose-300">{{ $row['checked_out_at'] ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $row['status'] ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $row['punch_source'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No staff with Staff ID found for {{ $date }}.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
