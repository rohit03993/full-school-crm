@php
    $summary = $this->attendanceSummary();
    $rows = $this->reportRows();
    $isClass = $attendanceView === 'class';
    $percentIndex = $isClass ? 6 : 9;
    $toneClass = [
        'low' => 'text-red-600 dark:text-red-400',
        'mid' => 'text-amber-600 dark:text-amber-400',
        'high' => 'text-green-600 dark:text-green-400',
    ];
    $barClass = [
        'low' => 'bg-red-500',
        'mid' => 'bg-amber-500',
        'high' => 'bg-green-500',
    ];
@endphp

<div class="space-y-4">
    <section class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ is_array($report) ? ($report['title'] ?? 'Attendance') : 'Attendance' }}</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Red is under 75%. Amber is 75% to 89%. Green is 90% and above.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="setAttendanceView('student')" @class(['crm-wa-analytics__preset', 'crm-wa-analytics__preset--active' => ! $isClass])>Student wise</button>
                <button type="button" wire:click="setAttendanceView('class')" @class(['crm-wa-analytics__preset', 'crm-wa-analytics__preset--active' => $isClass])>Class wise</button>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @foreach (['this_month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom dates'] as $key => $label)
                <button type="button" wire:click="applyPreset('{{ $key }}')" @class(['crm-wa-analytics__preset', 'crm-wa-analytics__preset--active' => $datePreset === $key])>{{ $label }}</button>
            @endforeach
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">From</span>
                <input type="date" wire:model="dateFrom" wire:change="markCustomDates" class="crm-wa-analytics__date-input" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">To</span>
                <input type="date" wire:model="dateTo" wire:change="markCustomDates" class="crm-wa-analytics__date-input" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Class</span>
                <select wire:model.live="courseId" class="crm-wa-analytics__date-input">
                    <option value="">All classes</option>
                    @foreach ($this->courseOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Batch</span>
                <select wire:model="batchId" class="crm-wa-analytics__date-input">
                    <option value="">All batches</option>
                    @foreach ($this->batchOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm sm:col-span-2">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Student name or mobile</span>
                <input type="text" wire:model="studentSearch" placeholder="Leave blank for everyone" class="crm-wa-analytics__date-input" />
            </label>
            @if (! $isClass)
                <label class="block text-sm">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Attendance below %</span>
                    <input type="number" min="1" max="100" wire:model="maxPercentage" placeholder="All" class="crm-wa-analytics__date-input" />
                </label>
            @endif
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <button type="button" wire:click="applyReport" wire:loading.attr="disabled" class="crm-wa-analytics__apply-btn">
                <span wire:loading.remove wire:target="applyReport">Apply</span>
                <span wire:loading wire:target="applyReport">Updating…</span>
            </button>
            <button type="button" wire:click="clearReportFilters" class="crm-wa-analytics__preset">Clear</button>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $isClass ? 'Classes' : 'Students' }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $isClass ? $summary['count'] : $summary['students'] }}</p>
        </article>
        <article class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Average %</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $summary['average'] === null ? '—' : $summary['average'] }}</p>
        </article>
        <article class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $isClass ? 'Classes under 75%' : 'Students under 75%' }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-red-600 dark:text-red-400">{{ $summary['under'] }}</p>
        </article>
        <article class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Lowest %</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $summary['lowest'] === null ? '—' : $summary['lowest'] }}</p>
        </article>
    </section>

    <section class="rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ count(is_array($report) ? ($report['rows'] ?? []) : []) }} row(s)
                @if ($rows->total() > 0 && $rows->hasPages())
                    · Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }}
                @endif
            </p>
            @if ($this->canExportReport())
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="exportExcel" class="crm-wa-analytics__apply-btn">Excel</button>
                    <button type="button" wire:click="exportCsv" class="crm-wa-analytics__preset">CSV</button>
                    <button type="button" wire:click="exportPdf" class="crm-wa-analytics__preset">PDF</button>
                </div>
            @endif
        </div>

        <div class="mt-4 space-y-2 md:hidden">
            @forelse ($rows as $row)
                @php
                    $tone = $this->percentTone($row[$percentIndex] ?? null);
                    $width = min(100, max(0, (float) ($row[$percentIndex] ?? 0)));
                @endphp
                <article class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row[0] }}</p>
                            <p class="text-xs text-gray-500">{{ $isClass ? ($row[1].' students') : ($row[2].' · '.$row[3]) }}</p>
                        </div>
                        <p @class(['text-lg font-bold tabular-nums', $toneClass[$tone] ?? 'text-gray-950 dark:text-white'])>{{ $row[$percentIndex] }}%</p>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div @class(['h-full rounded-full', $barClass[$tone] ?? 'bg-gray-400']) style="width: {{ $width }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                        @if ($isClass)
                            Present {{ $row[2] }} · Leave {{ $row[3] }} · Absent {{ $row[4] }} · Days {{ $row[5] }}
                        @else
                            Roll {{ $row[1] }} · {{ $row[4] }} · Present {{ $row[5] }} · Leave {{ $row[6] }} · Absent {{ $row[7] }}
                        @endif
                    </p>
                </article>
            @empty
                <p class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    No students match these filters. Clear the filters or widen the dates.
                </p>
            @endforelse
        </div>

        <div class="mt-4 hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm">
                <thead class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        @if ($isClass)
                            <th class="px-3 py-2">Class</th>
                            <th class="px-3 py-2">Students</th>
                        @else
                            <th class="px-3 py-2">Student</th>
                            <th class="px-3 py-2">Roll</th>
                            <th class="px-3 py-2">Class</th>
                            <th class="px-3 py-2">Batch</th>
                            <th class="px-3 py-2">Mobile</th>
                        @endif
                        <th class="px-3 py-2">Present</th>
                        <th class="px-3 py-2">Leave</th>
                        <th class="px-3 py-2">Absent</th>
                        <th class="px-3 py-2">Days</th>
                        <th class="px-3 py-2">%</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($rows as $row)
                        @php
                            $tone = $this->percentTone($row[$percentIndex] ?? null);
                            $width = min(100, max(0, (float) ($row[$percentIndex] ?? 0)));
                            $rank = ($rows->firstItem() ?? 1) + $loop->index;
                        @endphp
                        <tr>
                            <td class="px-3 py-2.5 text-gray-400">{{ $rank }}</td>
                            @if ($isClass)
                                <td class="px-3 py-2.5 font-semibold text-gray-950 dark:text-white">{{ $row[0] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[1] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[2] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[3] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[4] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[5] }}</td>
                            @else
                                <td class="px-3 py-2.5 font-semibold text-gray-950 dark:text-white">{{ $row[0] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[1] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[2] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[3] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[4] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[5] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[6] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[7] }}</td>
                                <td class="px-3 py-2.5 text-gray-700 dark:text-gray-300">{{ $row[8] }}</td>
                            @endif
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                        <div @class(['h-full rounded-full', $barClass[$tone] ?? 'bg-gray-400']) style="width: {{ $width }}%"></div>
                                    </div>
                                    <span @class(['font-semibold tabular-nums', $toneClass[$tone] ?? 'text-gray-950'])>{{ $row[$percentIndex] }}</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isClass ? 8 : 11 }}" class="px-3 py-8 text-center text-gray-500">
                                No students match these filters. Clear the filters or widen the dates.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        @endif
    </section>
</div>
