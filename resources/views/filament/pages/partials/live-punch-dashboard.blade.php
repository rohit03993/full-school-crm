@php
    /** @var bool $punchTableReady */
    /** @var array{stats: array{total: int, inside: int, out: int}, rows: list<array<string, mixed>>} $dashboard */
    /** @var array<string, mixed> $batchRoster */
    /** @var string|null $lastRefreshedAt */
    /** @var string|null $activeStateFilter */
    /** @var string|null $highlightRoll */
    $stats = $dashboard['stats'] ?? ['total' => 0, 'inside' => 0, 'out' => 0];
    $rows = $dashboard['rows'] ?? [];

    $statTiles = [
        [
            'key' => null,
            'label' => 'With punches',
            'hint' => 'Tap to show all',
            'value' => $stats['total'],
            'accent' => 'from-slate-500/10 to-slate-500/5 ring-slate-500/20',
            'valueClass' => 'text-gray-950 dark:text-white',
            'activeRing' => 'ring-2 ring-primary-500',
        ],
        [
            'key' => 'IN',
            'label' => 'Inside now',
            'hint' => 'Tap to filter',
            'value' => $stats['inside'],
            'accent' => 'from-emerald-500/15 to-emerald-500/5 ring-emerald-500/25',
            'valueClass' => 'text-emerald-600 dark:text-emerald-400',
            'activeRing' => 'ring-2 ring-emerald-500',
        ],
        [
            'key' => 'OUT',
            'label' => 'Checked out',
            'hint' => 'Tap to filter',
            'value' => $stats['out'],
            'accent' => 'from-rose-500/15 to-rose-500/5 ring-rose-500/25',
            'valueClass' => 'text-rose-600 dark:text-rose-400',
            'activeRing' => 'ring-2 ring-rose-500',
        ],
    ];

    $chipClasses = [
        'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
        'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300',
        'warning' => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
        'muted' => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
    ];
@endphp

<div wire:poll.30s="refreshDashboard" class="space-y-5">
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($statTiles as $tile)
            @php
                $isActive = ($tile['key'] === null && $activeStateFilter === null)
                    || ($tile['key'] !== null && $activeStateFilter === $tile['key']);
            @endphp
            <button
                type="button"
                wire:click="filterByState(@js($tile['key']))"
                @class([
                    'fi-section group rounded-2xl bg-gradient-to-br p-4 text-left shadow-sm ring-1 transition hover:-translate-y-0.5 hover:shadow-md sm:p-5',
                    $tile['accent'],
                    $isActive ? $tile['activeRing'] : 'ring-gray-950/5 dark:ring-white/10',
                ])
            >
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</p>
                <p class="mt-2 text-4xl font-extrabold tabular-nums {{ $tile['valueClass'] }}">{{ $tile['value'] }}</p>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $selectedDateLabel }} · {{ $tile['hint'] }}</p>
            </button>
        @endforeach
    </div>

    <div class="fi-section rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:items-end sm:justify-between sm:px-5">
            <div class="min-w-0 flex-1">
                <label for="live-punch-quick-search" class="text-sm font-semibold text-gray-950 dark:text-white">Quick find student</label>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Roll, mobile, or name</p>
                <div class="relative mt-2">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </span>
                    <input
                        id="live-punch-quick-search"
                        type="search"
                        wire:model.live.debounce.400ms="quickSearch"
                        wire:keydown.enter="searchAndFocus"
                        placeholder="e.g. 101 or 9876543210"
                        class="w-full rounded-xl border-gray-200 bg-gray-50 py-2.5 pl-10 pr-4 text-sm shadow-inner focus:border-primary-500 focus:bg-white focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:focus:bg-gray-950"
                    />
                </div>
                @if (filled($highlightRoll))
                    <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-primary-500/10 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:text-primary-300">
                        Highlighting {{ $highlightRoll }}
                    </p>
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button
                    type="button"
                    wire:click="searchAndFocus"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-60"
                >
                    Find
                </button>
                @if (filled($quickSearch) || filled($highlightRoll))
                    <button type="button" wire:click="clearQuickSearch" class="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200">
                        Clear
                    </button>
                @endif
                <button
                    type="button"
                    wire:click="refreshDashboard"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                >
                    <svg wire:loading.remove wire:target="refreshDashboard" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg>
                    <span wire:loading.remove wire:target="refreshDashboard">Refresh</span>
                    <span wire:loading wire:target="refreshDashboard">…</span>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm text-gray-600 dark:text-gray-300 sm:px-5">
            <p>
                <strong class="text-gray-950 dark:text-white">{{ count($rows) }}</strong> student(s)
                @if ($activeStateFilter)
                    · <span class="font-medium">{{ $activeStateFilter === 'IN' ? 'Inside' : 'Checked out' }}</span>
                @endif
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">IN → Present · next gap → OUT</p>
        </div>
    </div>

    @include('filament.pages.partials.live-punch-batch-roster', [
        'batchRoster' => $batchRoster,
        'selectedDateLabel' => $selectedDateLabel,
    ])

    @if ($rows === [])
        <div class="fi-section rounded-2xl px-6 py-14 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 dark:bg-white/10">
                <svg class="h-7 w-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.21a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
            </div>
            <p class="mt-4 text-base font-semibold text-gray-950 dark:text-white">No punches yet</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Change date or batch, or switch to Manual batch to mark attendance by hand.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php
                    $pairs = $row['pairs'] ?? [];
                    $chips = $row['whatsapp_chips'] ?? ['in' => ['label' => 'Not sent', 'tone' => 'muted'], 'out' => ['label' => 'Not sent', 'tone' => 'muted']];
                    $isHighlighted = filled($highlightRoll) && strtoupper((string) $highlightRoll) === strtoupper((string) $row['roll']);
                    $isInside = $row['current_state'] === 'IN';
                @endphp
                <div
                    id="punch-row-{{ $row['roll'] }}"
                    x-data="{ open: {{ ($loop->first || $isHighlighted) ? 'true' : 'false' }} }"
                    @if ($isHighlighted)
                        x-init="$nextTick(() => { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); })"
                    @endif
                    @class([
                        'fi-section overflow-hidden rounded-2xl shadow-sm ring-1 transition',
                        'ring-primary-500 ring-2' => $isHighlighted,
                        'ring-gray-950/5 dark:ring-white/10' => ! $isHighlighted,
                    ])
                >
                    <div @class([
                        'h-1 w-full',
                        'bg-gradient-to-r from-emerald-500 to-emerald-400' => $isInside,
                        'bg-gradient-to-r from-gray-300 to-gray-200 dark:from-gray-600 dark:to-gray-700' => ! $isInside,
                    ])></div>

                    <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                        <button type="button" x-on:click="open = ! open" class="min-w-0 flex-1 text-left">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-lg bg-primary-500/10 px-2 py-0.5 font-mono text-xs font-bold text-primary-700 dark:text-primary-300">{{ $row['roll'] }}</span>
                                @if ($isInside)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Inside
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">Out</span>
                                @endif
                                @unless ($row['is_mapped'])
                                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Unmapped</span>
                                @endunless
                                @if (filled($row['total_duration'] ?? null))
                                    <span class="rounded-full bg-sky-500/10 px-2.5 py-0.5 text-xs font-semibold text-sky-800 dark:text-sky-200">{{ $row['total_duration'] }}</span>
                                @endif
                            </div>
                            <p class="mt-2 truncate text-lg font-bold text-gray-950 dark:text-white">{{ $row['student_name'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $row['batch_name'] ?? 'No active batch' }}
                                @if (filled($row['mobile'])) · {{ $row['mobile'] }} @endif
                                @if (filled($row['last_device'] ?? null)) · {{ $row['last_device'] }} @endif
                            </p>
                        </button>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($row['is_mapped'] && filled($row['profile_url'] ?? null))
                                <a href="{{ $row['profile_url'] }}" class="rounded-xl bg-primary-500/10 px-3 py-2 text-xs font-bold text-primary-700 ring-1 ring-primary-500/20 hover:bg-primary-500/15 dark:text-primary-300">Profile</a>
                            @else
                                <a href="{{ $row['find_student_url'] }}" class="rounded-xl bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-900 ring-1 ring-amber-500/20 dark:text-amber-200">Link student</a>
                            @endif
                            <button type="button" x-on:click="open = ! open" class="rounded-xl bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                <span x-text="open ? 'Less' : 'Details'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="open" x-collapse class="border-t border-gray-100 bg-gray-50/50 px-4 py-4 dark:border-white/10 dark:bg-white/[0.02] sm:px-5">
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($pairs as $index => $pair)
                                <div class="rounded-xl bg-white p-3 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Visit {{ $index + 1 }}</p>
                                        @if (filled($pair['duration_label'] ?? null))
                                            <span class="rounded-full bg-sky-500/10 px-2 py-0.5 text-[10px] font-bold text-sky-700 dark:text-sky-300">{{ $pair['duration_label'] }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase text-emerald-600 dark:text-emerald-400">In</p>
                                            <p class="mt-0.5 font-mono text-sm font-bold text-gray-950 dark:text-white">{{ $pair['in'] ?? '—' }}</p>
                                            @if (! empty($pair['is_manual_in']))
                                                <p class="mt-1 text-[10px] font-semibold text-amber-600">Manual</p>
                                            @elseif (filled($pair['device_in'] ?? null))
                                                <p class="mt-1 truncate text-[10px] text-gray-500">{{ $pair['device_in'] }}</p>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-semibold uppercase text-rose-600 dark:text-rose-400">Out</p>
                                            <p class="mt-0.5 font-mono text-sm font-bold text-gray-950 dark:text-white">{{ $pair['out'] ?? '—' }}</p>
                                            @if (! empty($pair['is_auto_out']))
                                                <p class="mt-1 text-[10px] font-semibold text-gray-500">Auto OUT</p>
                                            @elseif (! empty($pair['is_manual_out']))
                                                <p class="mt-1 text-[10px] font-semibold text-amber-600">Manual</p>
                                            @elseif (filled($pair['device_out'] ?? null))
                                                <p class="mt-1 truncate text-[10px] text-gray-500">{{ $pair['device_out'] }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($row['is_mapped'])
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" wire:click="markManualPunch('{{ $row['roll'] }}', 'IN')" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-500">Manual IN</button>
                                <button type="button" wire:click="markManualPunch('{{ $row['roll'] }}', 'OUT')" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-500">Manual OUT</button>
                            </div>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach (['in' => 'IN WhatsApp', 'out' => 'OUT WhatsApp'] as $key => $label)
                                @php $chip = $chips[$key] ?? ['label' => 'Not sent', 'tone' => 'muted']; @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $chipClasses[$chip['tone']] ?? $chipClasses['muted'] }}">
                                    @if ($chip['tone'] === 'success') ✓ @elseif ($chip['tone'] === 'danger') ✕ @elseif ($chip['tone'] === 'warning') ⏳ @endif
                                    {{ $label }}: {{ $chip['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
