@php
    use App\Enums\ProfilePhase;

    $phase = $profile['phase'];
    $timeline = collect($activityTimeline ?? []);
    $grouped = $timeline->groupBy('occurred_date');
@endphp

<div class="space-y-3 sm:space-y-5">
    <div class="fi-student-profile-card overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 sm:rounded-2xl dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-3 py-3 sm:px-6 sm:py-3.5 dark:border-white/10">
            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Activity timeline</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                Student journey — newest first. Tap a stop to open that tab.
            </p>
        </div>

        @if (! ($activityTimelineLoaded ?? false))
            <p class="px-3 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">Loading activity…</p>
        @elseif ($timeline->isEmpty())
            <p class="px-3 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">
                No activity recorded yet for this student.
            </p>
        @else
            <div class="px-2.5 py-3 sm:px-6 sm:py-5">
                @foreach ($grouped as $date => $items)
                    <div class="mb-2 mt-4 first:mt-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            {{ $items->first()['occurred_date_label'] ?? $date }}
                        </p>
                    </div>

                    <div class="relative">
                        {{-- Route spine — narrower time column on phones so notes get width --}}
                        <div class="pointer-events-none absolute bottom-2 top-2 left-[3.85rem] w-0.5 bg-gray-200 sm:left-[5.25rem] dark:bg-white/15" aria-hidden="true"></div>

                        <ul class="space-y-0">
                            @foreach ($items as $itemIndex => $item)
                                @php
                                    $dot = match ($item['type'] ?? '') {
                                        'call' => 'border-sky-500 bg-sky-500',
                                        'visit' => 'border-violet-500 bg-violet-500',
                                        'case' => 'border-amber-500 bg-amber-500',
                                        'fee' => 'border-emerald-500 bg-emerald-500',
                                        'whatsapp' => 'border-green-500 bg-green-500',
                                        'attendance' => 'border-indigo-500 bg-indigo-500',
                                        'homework' => 'border-orange-500 bg-orange-500',
                                        'exam' => 'border-fuchsia-500 bg-fuchsia-500',
                                        'certificate' => 'border-teal-500 bg-teal-500',
                                        'document' => 'border-slate-500 bg-slate-500',
                                        'enrollment', 'batch' => 'border-primary-500 bg-primary-500',
                                        default => 'border-gray-400 bg-white dark:bg-gray-900',
                                    };
                                    $clickable = filled($item['tab'] ?? null) && ($item['tab'] ?? '') !== 'overview';
                                @endphp
                                <li
                                    @if ($clickable)
                                        wire:click="openActivityTimelineTab(@js($item['tab']))"
                                        role="button"
                                        tabindex="0"
                                        class="group relative grid cursor-pointer grid-cols-[3.5rem_1.25rem_minmax(0,1fr)] gap-x-1.5 py-2.5 sm:grid-cols-[4.75rem_1.5rem_minmax(0,1fr)] sm:gap-x-3 sm:py-3"
                                    @else
                                        class="relative grid grid-cols-[3.5rem_1.25rem_minmax(0,1fr)] gap-x-1.5 py-2.5 sm:grid-cols-[4.75rem_1.5rem_minmax(0,1fr)] sm:gap-x-3 sm:py-3"
                                    @endif
                                >
                                    {{-- Left: time (like scheduled arrival) --}}
                                    <div class="pt-0.5 text-right">
                                        <p class="text-xs font-semibold tabular-nums text-gray-900 sm:text-sm dark:text-gray-100">
                                            {{ $item['occurred_at_label'] }}
                                        </p>
                                        <p class="mt-0.5 text-[9px] font-bold uppercase tracking-wide text-gray-400 sm:text-[10px] dark:text-gray-500">
                                            {{ $item['category'] ?? 'EVENT' }}
                                        </p>
                                    </div>

                                    {{-- Center: station node --}}
                                    <div class="relative flex justify-center pt-1">
                                        <span
                                            @class([
                                                'relative z-10 h-3.5 w-3.5 shrink-0 rounded-full border-2 ring-4 ring-white dark:ring-gray-900',
                                                $dot,
                                            ])
                                            aria-hidden="true"
                                        ></span>
                                    </div>

                                    {{-- Right: event name + details --}}
                                    <div class="min-w-0 rounded-lg px-1 transition group-hover:bg-gray-50 dark:group-hover:bg-white/5 sm:px-2">
                                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-0.5">
                                            <p class="text-sm font-semibold text-gray-950 group-hover:text-primary-700 dark:text-white dark:group-hover:text-primary-300">
                                                {{ $item['title'] }}
                                            </p>
                                            @if (filled($item['staff_name'] ?? null))
                                                <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ $item['staff_name'] }}</p>
                                            @endif
                                        </div>
                                        @if (filled($item['summary'] ?? null))
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $item['summary'] }}</p>
                                        @endif
                                        @if (filled($item['detail'] ?? null))
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</p>
                                        @endif
                                        @if ($clickable)
                                            <p class="mt-1 text-[11px] font-medium text-primary-600 dark:text-primary-400">
                                                View in {{ str_replace('_', ' ', (string) $item['tab']) }} →
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if ($activityTimelineHasMore ?? false)
                <div class="border-t border-gray-100 px-3 py-3 dark:border-white/10 sm:px-6">
                    <button
                        type="button"
                        wire:click="loadMoreActivityTimeline"
                        class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Load more activity
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if (($phase === ProfilePhase::Enrolled || $phase === ProfilePhase::ActiveStudent) && ($profile['dossier'] ?? null))
        @include('filament.pages.partials.student-profile-overview-dossier', [
            'record' => $record,
            'profile' => $profile,
            'enquiries' => $enquiries,
        ])
    @elseif ($phase->isLeadStage())
        <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Contact</h3>
                </div>
                <dl class="grid gap-3 px-4 py-4 text-sm sm:grid-cols-2 sm:gap-4 sm:px-6 sm:pb-6">
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->name }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Mobile</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ $record->mobile }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</dt><dd class="mt-0.5 font-medium text-gray-950 dark:text-white">{{ collect([$record->address, $record->city, $record->state, $record->pincode])->filter()->implode(', ') ?: '—' }}</dd></div>
                </dl>
            </div>

            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Enquiries</h3>
                </div>
                @if ($enquiries->isEmpty())
                    <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No enquiries yet.</p>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($enquiries->take(5) as $enquiry)
                            <div class="px-4 py-3 sm:px-6">
                                <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $enquiry->course?->name ?? 'Course not selected' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $enquiry->enquiry_number }}
                                    · {{ $enquiry->latest_visit_status?->label() ?? '—' }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
