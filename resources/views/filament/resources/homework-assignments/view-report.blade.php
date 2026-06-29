@php
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, array{student: \App\Models\Student, viewed: bool, viewed_at: ?string}> $report */
@endphp

<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">View tracking</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Students in this batch who have a mobile number. View is recorded when they open homework in the student portal.
        </p>
        @if ($report->total() > 0)
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Showing {{ $report->firstItem() }}–{{ $report->lastItem() }} of {{ $report->total() }} students
            </p>
        @endif
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Student</th>
                    <th class="px-4 py-3">{{ \App\Support\StudentLabels::rollNumberLabel() }}</th>
                    <th class="px-4 py-3">Mobile</th>
                    <th class="px-4 py-3">Viewed</th>
                    <th class="px-4 py-3">Viewed at</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($report as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['student']->name }}</td>
                        <td class="px-4 py-3 font-mono text-gray-700 dark:text-gray-300">
                            {{ $row['student']->activeEnrollment?->enrollment_number ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['student']->mobile }}</td>
                        <td class="px-4 py-3">
                            @if ($row['viewed'])
                                <span class="text-emerald-600 dark:text-emerald-400">Yes</span>
                            @else
                                <span class="text-gray-400">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['viewed_at'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            No students with a mobile number in this batch.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($report->hasPages())
        <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
            {{ $report->links() }}
        </div>
    @endif
</div>
