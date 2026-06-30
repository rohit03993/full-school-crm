<x-filament-widgets::widget class="fi-dashboard-hero-widget">
    <div class="fi-dashboard-hero relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 via-amber-600 to-orange-700 p-5 shadow-lg ring-1 ring-black/5 sm:p-6 lg:p-8">
        <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-10 h-48 w-48 rounded-full bg-orange-900/20 blur-2xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-100/90">
                    {{ $instituteName }}
                </p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    Welcome back, {{ $userName }}
                </h2>
                <p class="mt-1 text-sm text-amber-50/90 sm:text-base">
                    {{ $todayLabel }}
                </p>
                @if ($tagline)
                    <p class="mt-2 max-w-xl text-sm text-amber-100/80">
                        {{ $tagline }}
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($isOwner)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                            <x-filament::icon icon="heroicon-m-user-group" class="h-3.5 w-3.5" />
                            {{ $activeStudents }} enrolled
                        </span>
                        @if ($showAttendanceSummary)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                                <x-filament::icon icon="heroicon-m-check-circle" class="h-3.5 w-3.5" />
                                {{ $presentToday }} present today
                            </span>
                        @endif
                        @if ($showFeesSummary)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                                <x-filament::icon icon="heroicon-m-banknotes" class="h-3.5 w-3.5" />
                                ₹{{ number_format($feeToday, 0) }} collected
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-3.5 w-3.5" />
                                ₹{{ number_format($pendingFeesTotal, 0) }} pending fees
                            </span>
                        @endif
                        @if ($showAdmissionsSummary && $pendingAdmissions > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/30 backdrop-blur-sm">
                                <x-filament::icon icon="heroicon-m-clipboard-document-check" class="h-3.5 w-3.5" />
                                {{ $pendingAdmissions }} pending admissions
                            </span>
                        @endif
                        @if ($showEnquirySummary && $todayEnquiries > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                                <x-filament::icon icon="heroicon-m-inbox-arrow-down" class="h-3.5 w-3.5" />
                                {{ $todayEnquiries }} new leads today
                            </span>
                        @endif
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20 backdrop-blur-sm">
                            <x-filament::icon icon="heroicon-m-phone" class="h-3.5 w-3.5" />
                            Your calling workspace
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid w-full grid-cols-2 gap-2 sm:gap-3 lg:max-w-md lg:shrink-0">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        wire:navigate
                        class="group flex touch-manipulation flex-col gap-2 rounded-xl bg-white/10 p-3 ring-1 ring-white/20 backdrop-blur-sm transition hover:bg-white/20 hover:shadow-md active:scale-[0.98] sm:p-4"
                    >
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 ring-1 ring-white/25 transition group-hover:bg-white/30">
                            <x-filament::icon :icon="$action['icon']" class="h-5 w-5 text-white" />
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-white">{{ $action['label'] }}</span>
                            <span class="mt-0.5 block text-[11px] leading-tight text-amber-50/80 sm:text-xs">
                                {{ $action['description'] }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
