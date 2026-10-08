@php
    $report = $this->report();
    $staffRows = $report['staff'] ?? [];
    $recentRows = $report['recent'] ?? [];
    $homeworkSends = 0;
    $homeworkResends = 0;
    $examSends = 0;
    $examResends = 0;

    foreach ($staffRows as $staffRow) {
        $homeworkSends += (int) ($staffRow['homework_sends'] ?? 0);
        $homeworkResends += (int) ($staffRow['homework_resends'] ?? 0);
        $examSends += (int) ($staffRow['exam_sends'] ?? 0);
        $examResends += (int) ($staffRow['exam_resends'] ?? 0);
    }
@endphp

<div class="space-y-6">
    <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="grid gap-3 sm:grid-cols-2 sm:items-end">
            <label class="block text-sm">
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">From</span>
                <input type="date" wire:model.live="dateFrom" class="fi-crm-input mt-2 block w-full" />
            </label>
            <label class="block text-sm">
                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">To</span>
                <input type="date" wire:model.live="dateTo" class="fi-crm-input mt-2 block w-full" />
            </label>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Each number is a click, not a parent. One class with 40 parents is still 1 send.</p>
    </section>

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Homework sends', 'value' => $homeworkSends, 'tone' => 'text-emerald-700 dark:text-emerald-400'],
            ['label' => 'Homework resends', 'value' => $homeworkResends, 'tone' => 'text-amber-700 dark:text-amber-400'],
            ['label' => 'Exam mark sends', 'value' => $examSends, 'tone' => 'text-emerald-700 dark:text-emerald-400'],
            ['label' => 'Exam mark resends', 'value' => $examResends, 'tone' => 'text-amber-700 dark:text-amber-400'],
        ] as $card)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                <p @class(['mt-1 text-2xl font-bold tabular-nums', $card['tone']])>{{ $card['value'] }}</p>
            </div>
        @endforeach
    </section>

    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">By staff</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Staff</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Homework sends</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Homework resends</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Exam mark sends</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Exam mark resends</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($staffRows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                            @foreach ([
                                'homework_sends' => 'emerald',
                                'homework_resends' => 'amber',
                                'exam_sends' => 'emerald',
                                'exam_resends' => 'amber',
                            ] as $countKey => $tone)
                                @php
                                    $count = (int) ($row[$countKey] ?? 0);
                                @endphp
                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if ($count > 0 && $tone === 'amber')
                                        <span class="inline-flex min-w-8 justify-center rounded-full bg-amber-50 px-2 py-0.5 text-sm font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">{{ $count }}</span>
                                    @elseif ($count > 0)
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $count }}</span>
                                    @else
                                        <span class="text-gray-300 dark:text-gray-600">0</span>
                                    @endif
                                </td>
                            @endforeach
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

    <section class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-white/5">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Each click</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">When</th>
                        <th class="px-4 py-2.5 font-semibold">Staff</th>
                        <th class="px-4 py-2.5 font-semibold">Type</th>
                        <th class="px-4 py-2.5 font-semibold">Class or exam</th>
                        <th class="px-4 py-2.5 font-semibold">Click</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Parents</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @forelse ($recentRows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['at'] }}</td>
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['kind'] }}</td>
                            <td class="px-4 py-3 text-gray-950 dark:text-white">{{ $row['place'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['repeat'] === 'Resend')
                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30">Resend</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30">First send</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $row['parents'] }}</td>
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
