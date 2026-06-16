<x-filament-widgets::widget class="fi-dashboard-list-widget">
    <x-filament::section
        heading="Pending Admissions"
        description="Submissions awaiting review"
        class="fi-dashboard-panel"
    >
        @if ($admissions->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-success-500/10 ring-1 ring-success-500/20">
                    <x-filament::icon icon="heroicon-o-check-badge" class="h-7 w-7 text-success-500" />
                </div>
                <p class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">All caught up</p>
                <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                    No admissions waiting for verification right now.
                </p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($admissions as $admission)
                    <a
                        href="{{ $viewUrl($admission->id) }}"
                        class="group flex w-full touch-manipulation items-center gap-3 rounded-xl border border-gray-200/80 bg-white p-3.5 text-left shadow-sm transition hover:border-warning-400/60 hover:bg-warning-500/[0.03] hover:shadow-md active:scale-[0.99] dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-warning-500/50 sm:gap-4 sm:p-4"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-warning-500/15 to-amber-600/5 ring-1 ring-warning-500/10">
                            <x-filament::icon icon="heroicon-o-academic-cap" class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="truncate text-sm font-bold text-gray-950 dark:text-white sm:text-base">
                                    {{ $admission->student?->name ?? 'Unknown' }}
                                </p>
                                <span @class([
                                    'inline-flex shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    'bg-amber-500/15 text-amber-700 ring-1 ring-amber-500/20 dark:text-amber-400' => $admission->status?->value === 'submitted',
                                    'bg-orange-500/15 text-orange-700 ring-1 ring-orange-500/20 dark:text-orange-400' => $admission->status?->value === 'verification_pending',
                                ])>
                                    {{ $admission->status?->label() }}
                                </span>
                            </div>

                            <p class="mt-0.5 text-xs font-semibold text-gray-600 dark:text-gray-300 sm:text-sm">
                                {{ $admission->admission_number }}
                                @if ($admission->enquiry?->course)
                                    <span class="font-normal text-gray-400">· {{ $admission->enquiry->course->name }}</span>
                                @endif
                            </p>

                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                @if ($admission->net_fee)
                                    ₹{{ number_format((float) $admission->net_fee, 0) }} net fee
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                @endif
                                {{ ($admission->submitted_at ?? $admission->created_at)?->diffForHumans(short: true) }}
                            </p>
                        </div>

                        <x-filament::icon
                            icon="heroicon-m-chevron-right"
                            class="h-5 w-5 shrink-0 text-gray-300 group-hover:text-warning-500 dark:text-gray-600"
                        />
                    </a>
                @endforeach
            </div>

            <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                <a
                    href="{{ $allAdmissionsUrl }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-50 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-500/10 dark:bg-white/5 dark:text-primary-400 dark:hover:bg-primary-500/10"
                >
                    View all admissions
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
