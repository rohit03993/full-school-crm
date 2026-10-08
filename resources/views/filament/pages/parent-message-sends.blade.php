@php
    $report = $this->report();
    $staffRows = $report['staff'] ?? [];
    $recentRows = $report['recent'] ?? [];
@endphp

<div class="space-y-6">
    <section class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">From</span>
                <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/15 dark:bg-gray-900" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">To</span>
                <input type="date" wire:model.live="dateTo" class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/15 dark:bg-gray-900" />
            </label>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Each number is a click, not a parent. One class with 40 parents is still 1 send.</p>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">By staff</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2">Staff</th>
                        <th class="px-4 py-2">Homework sends</th>
                        <th class="px-4 py-2">Homework resends</th>
                        <th class="px-4 py-2">Exam mark sends</th>
                        <th class="px-4 py-2">Exam mark resends</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($staffRows as $row)
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row['homework_sends'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row['homework_resends'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row['exam_sends'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row['exam_resends'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-sm text-gray-500">No homework or exam-mark sends in these dates.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/5">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Each click</h3>
            @if ($recentRows->total() > 0)
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $recentRows->firstItem() }}–{{ $recentRows->lastItem() }} of {{ $recentRows->total() }}</p>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Staff</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Class or exam</th>
                        <th class="px-4 py-2">Click</th>
                        <th class="px-4 py-2">Parents</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($recentRows as $row)
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $row['at'] }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                            <td class="px-4 py-2.5">{{ $row['kind'] }}</td>
                            <td class="px-4 py-2.5">{{ $row['place'] }}</td>
                            <td class="px-4 py-2.5">{{ $row['repeat'] }}</td>
                            <td class="px-4 py-2.5 tabular-nums">{{ $row['parents'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-sm text-gray-500">No clicks in these dates.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($recentRows->hasPages())
            <div class="border-t border-gray-100 px-4 py-3 dark:border-white/10">
                {{ $recentRows->links() }}
            </div>
        @endif
    </section>
</div>
