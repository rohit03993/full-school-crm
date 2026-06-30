@php
    /** @var array{enabled: bool, batch_name: ?string, present: list<array<string, mixed>>, absent: list<array<string, mixed>>, counts: array{total: int, present: int, absent: int}} $batchRoster */
@endphp

@if ($batchRoster['enabled'] ?? false)
    <div class="fi-section space-y-4 rounded-2xl p-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Batch roll call</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $batchRoster['batch_name'] }} · {{ $selectedDateLabel }}
                    · {{ $batchRoster['counts']['present'] }} present · {{ $batchRoster['counts']['absent'] }} absent
                </p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 dark:border-emerald-500/20 dark:bg-emerald-500/5">
                <div class="flex items-center justify-between border-b border-emerald-200 px-4 py-3 dark:border-emerald-500/20">
                    <h4 class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Present today</h4>
                    <span class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-xs font-bold text-white">{{ $batchRoster['counts']['present'] }}</span>
                </div>
                <div class="max-h-[28rem] space-y-2 overflow-y-auto p-3">
                    @forelse ($batchRoster['present'] as $row)
                        <div class="rounded-xl bg-white p-3 ring-1 ring-emerald-100 dark:bg-gray-900 dark:ring-emerald-500/20">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $row['student_name'] }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-primary-600 dark:text-primary-400">{{ $row['roll'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        IN {{ $row['pairs'][0]['in'] ?? '—' }}
                                        @if (filled($row['pairs'][0]['out'] ?? null))
                                            · OUT {{ $row['pairs'][0]['out'] }}
                                        @endif
                                        @if (filled($row['pairs'][0]['duration_label'] ?? null))
                                            · {{ $row['pairs'][0]['duration_label'] }}
                                        @endif
                                    </p>
                                    @if (filled($row['last_device'] ?? null))
                                        <p class="mt-1 text-[11px] text-gray-400">Device: {{ $row['last_device'] }}</p>
                                    @endif
                                </div>
                                <a href="{{ $row['profile_url'] }}" class="shrink-0 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Profile</a>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No punches yet for this batch.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-rose-200 bg-rose-50/40 dark:border-rose-500/20 dark:bg-rose-500/5">
                <div class="flex items-center justify-between border-b border-rose-200 px-4 py-3 dark:border-rose-500/20">
                    <h4 class="text-sm font-semibold text-rose-800 dark:text-rose-300">Absent today</h4>
                    <span class="rounded-full bg-rose-600 px-2.5 py-0.5 text-xs font-bold text-white">{{ $batchRoster['counts']['absent'] }}</span>
                </div>
                <div class="max-h-[28rem] space-y-2 overflow-y-auto p-3">
                    @forelse ($batchRoster['absent'] as $row)
                        <div class="rounded-xl bg-white p-3 ring-1 ring-rose-100 dark:bg-gray-900 dark:ring-rose-500/20">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $row['student_name'] }}</p>
                                    <p class="mt-0.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $row['roll'] ?? 'No roll' }}</p>
                                    @if (filled($row['mobile'] ?? null))
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['mobile'] }}</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-col gap-1">
                                    <a href="{{ $row['profile_url'] }}" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Profile</a>
                                    @if (filled($row['roll'] ?? null))
                                        <button
                                            type="button"
                                            wire:click="markManualPunch('{{ $row['roll'] }}', 'IN')"
                                            class="text-left text-xs font-semibold text-emerald-700 hover:underline dark:text-emerald-300"
                                        >
                                            Mark IN
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Everyone in this batch has punched in.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif
