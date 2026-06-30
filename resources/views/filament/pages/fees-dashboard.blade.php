<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Collected today</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['collection_today'] ?? 0), 2) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">This month</p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) ($summary['collection_month'] ?? 0), 2) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending fees</p>
                <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">₹{{ number_format((float) ($summary['pending_fees_total'] ?? 0), 2) }}</p>
                @if ((float) ($summary['pending_penalties_total'] ?? 0) > 0)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">+ ₹{{ number_format((float) $summary['pending_penalties_total'], 2) }} late fees</p>
                @endif
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Overdue</p>
                <p class="mt-1 text-2xl font-bold text-red-700 dark:text-red-400">₹{{ number_format((float) ($summary['overdue_amount'] ?? 0), 2) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ (int) ($summary['overdue_students_count'] ?? 0) }} student(s) · {{ (int) ($summary['overdue_installment_count'] ?? 0) }} installment(s)</p>
            </div>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Defaulters</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Students with overdue installment balances</p>
                </div>
                <x-filament::button color="gray" wire:click="refreshDashboard" size="sm">
                    Refresh
                </x-filament::button>
            </div>

            @if ($defaulters->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No overdue installments right now.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-6">Student</th>
                                <th class="px-4 py-3 font-semibold">Course</th>
                                <th class="px-4 py-3 font-semibold">Due</th>
                                <th class="px-4 py-3 font-semibold">Days late</th>
                                <th class="px-4 py-3 font-semibold text-right">Overdue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($defaulters as $row)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                    <td class="px-4 py-3 sm:px-6">
                                        <a href="{{ $row['profile_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                            {{ $row['student_name'] }}
                                        </a>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $row['enrollment_number'] ?? '—' }}
                                            @if ($row['mobile'])
                                                · {{ $row['mobile'] }}
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['course_name'] ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        {{ $row['next_due_date'] ? \Illuminate\Support\Carbon::parse($row['next_due_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-semibold text-red-800 dark:text-red-300">
                                            {{ $row['days_overdue'] }} day(s)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-300">
                                        ₹{{ number_format((float) $row['pending_amount'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
