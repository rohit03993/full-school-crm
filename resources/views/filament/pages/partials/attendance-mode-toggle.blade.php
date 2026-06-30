@php
    /** @var 'live'|'manual' $viewMode */
    /** @var string|null $lastRefreshedAt */
    /** @var bool $punchTableReady */
@endphp

<div class="space-y-4">
    <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-4 py-4 dark:border-white/10 dark:from-gray-900 dark:to-gray-950 sm:px-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-primary-400">Daily attendance</p>
                    <h2 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Reception desk</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                        Live biometric punches or manual batch roll call — one place, parent WhatsApp on each action.
                    </p>
                </div>
                @if ($viewMode === 'live')
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-500/20 dark:text-emerald-300">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            </span>
                            Live · refreshes every 30s
                        </span>
                        @if ($lastRefreshedAt)
                            <span class="text-xs text-gray-500 dark:text-gray-400">Updated {{ $lastRefreshedAt }}</span>
                        @endif
                    </div>
                @else
                    <span class="inline-flex items-center rounded-full bg-sky-500/10 px-3 py-1.5 text-xs font-semibold text-sky-800 ring-1 ring-sky-500/20 dark:text-sky-200">
                        Manual P / A / L · WhatsApp on save
                    </span>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2 p-2 sm:grid-cols-2 sm:p-3">
            <button
                type="button"
                wire:click="switchMode('live')"
                @class([
                    'group relative overflow-hidden rounded-xl border px-4 py-4 text-left transition-all duration-200',
                    'border-primary-500 bg-primary-500 text-white shadow-md shadow-primary-500/20' => $viewMode === 'live',
                    'border-transparent bg-gray-50 hover:border-gray-200 hover:bg-white dark:bg-white/5 dark:hover:border-white/10 dark:hover:bg-white/10' => $viewMode !== 'live',
                ])
            >
                <div class="flex items-start gap-3">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-white/20' => $viewMode === 'live',
                        'bg-primary-500/10 text-primary-600 dark:text-primary-400' => $viewMode !== 'live',
                    ])>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold">Live punches</p>
                        <p @class([
                            'mt-0.5 text-xs leading-relaxed',
                            'text-primary-100' => $viewMode === 'live',
                            'text-gray-500 dark:text-gray-400' => $viewMode !== 'live',
                        ])>
                            Biometric IN/OUT · auto Present · parent WhatsApp
                        </p>
                    </div>
                </div>
            </button>

            <button
                type="button"
                wire:click="switchMode('manual')"
                @class([
                    'group relative overflow-hidden rounded-xl border px-4 py-4 text-left transition-all duration-200',
                    'border-primary-500 bg-primary-500 text-white shadow-md shadow-primary-500/20' => $viewMode === 'manual',
                    'border-transparent bg-gray-50 hover:border-gray-200 hover:bg-white dark:bg-white/5 dark:hover:border-white/10 dark:hover:bg-white/10' => $viewMode !== 'manual',
                ])
            >
                <div class="flex items-start gap-3">
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                        'bg-white/20' => $viewMode === 'manual',
                        'bg-primary-500/10 text-primary-600 dark:text-primary-400' => $viewMode !== 'manual',
                    ])>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c1.11 0 2.08.402 2.599 1M12 8.25h.008v.008H12V8.25zm0 2.25h.008v.008H12v-.008zm0 2.25h.008v.008H12v-.008z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold">Manual batch</p>
                        <p @class([
                            'mt-0.5 text-xs leading-relaxed',
                            'text-primary-100' => $viewMode === 'manual',
                            'text-gray-500 dark:text-gray-400' => $viewMode !== 'manual',
                        ])>
                            Manual batch · mark IN / A / L · OUT per student · IN/OUT WhatsApp
                        </p>
                    </div>
                </div>
            </button>
        </div>
    </div>

    @if ($viewMode === 'live' && ! $punchTableReady)
        <div class="flex gap-3 rounded-2xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-orange-50 p-4 shadow-sm dark:border-amber-500/30 dark:from-amber-500/10 dark:to-orange-500/5 sm:p-5">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
            </span>
            <div class="min-w-0 text-sm">
                <p class="font-semibold text-amber-950 dark:text-amber-100">Biometric table not connected</p>
                <p class="mt-1 text-amber-900/80 dark:text-amber-200/90">
                    EasyTimePro needs <code class="rounded bg-white/80 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">punch_logs</code> on this server.
                    <a href="{{ \App\Filament\Pages\ManageAttendanceBiometricPage::getUrl() }}" class="font-bold underline">Settings → Biometric Attendance</a> has the full setup guide.
                    Manual batch still works without the device.
                </p>
            </div>
        </div>
    @endif
</div>
