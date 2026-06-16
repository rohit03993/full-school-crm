<x-filament-widgets::widget class="fi-dashboard-list-widget">
    <x-filament::section
        heading="Recent Leads"
        description="Latest enquiries across all sources"
        class="fi-dashboard-panel"
    >
        @if ($enquiries->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-10 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-500/10 ring-1 ring-primary-500/20">
                    <x-filament::icon icon="heroicon-o-inbox" class="h-7 w-7 text-primary-500" />
                </div>
                <p class="mt-4 text-sm font-semibold text-gray-950 dark:text-white">No leads yet</p>
                <p class="mt-1 max-w-xs text-sm text-gray-500 dark:text-gray-400">
                    Website and walk-in enquiries will appear here.
                </p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($enquiries as $enquiry)
                    <a
                        href="{{ $profileUrl($enquiry->student_id) }}"
                        class="group flex w-full touch-manipulation items-center gap-3 rounded-xl border border-gray-200/80 bg-white p-3.5 text-left shadow-sm transition hover:border-primary-400/60 hover:bg-primary-500/[0.03] hover:shadow-md active:scale-[0.99] dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-primary-500/50 sm:gap-4 sm:p-4"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-500/15 to-primary-600/5 ring-1 ring-primary-500/10">
                            <x-filament::icon icon="heroicon-o-user" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="truncate text-sm font-bold text-gray-950 dark:text-white sm:text-base">
                                    {{ $enquiry->student?->name ?? 'Unknown' }}
                                </p>
                                <span @class([
                                    'inline-flex shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                    'bg-emerald-500/15 text-emerald-600 ring-1 ring-emerald-500/20 dark:text-emerald-400' => $enquiry->lead_source?->value === 'website',
                                    'bg-sky-500/15 text-sky-700 ring-1 ring-sky-500/20 dark:text-sky-400' => $enquiry->lead_source?->value === 'walk_in',
                                    'bg-gray-500/10 text-gray-600 ring-1 ring-gray-500/10 dark:text-gray-400' => ! in_array($enquiry->lead_source?->value, ['website', 'walk_in'], true),
                                ])>
                                    {{ $enquiry->lead_source?->label() ?? 'Lead' }}
                                </span>
                            </div>

                            <p class="mt-0.5 text-xs font-semibold text-primary-600 dark:text-primary-400 sm:text-sm">
                                {{ $enquiry->student?->mobile ?? '—' }}
                            </p>

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-mono font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $enquiry->enquiry_number }}
                                </span>
                                @if ($enquiry->course)
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    <span class="truncate">{{ $enquiry->course->name }}</span>
                                @endif
                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                <span>{{ $enquiry->created_at?->diffForHumans(short: true) }}</span>
                            </div>
                        </div>

                        <x-filament::icon
                            icon="heroicon-m-chevron-right"
                            class="h-5 w-5 shrink-0 text-gray-300 group-hover:text-primary-500 dark:text-gray-600"
                        />
                    </a>
                @endforeach
            </div>

            <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/10">
                <a
                    href="{{ $allLeadsUrl }}"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-50 py-2.5 text-sm font-semibold text-primary-600 transition hover:bg-primary-500/10 dark:bg-white/5 dark:text-primary-400 dark:hover:bg-primary-500/10"
                >
                    View all leads
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                </a>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
