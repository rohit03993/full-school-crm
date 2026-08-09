@if (! $homeworkTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading homework…</p>
@else
    <div class="space-y-6">
        <div class="space-y-3">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Homework check marks</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Done / Not Done marks from class homework check.</p>
            </div>

            @if (($notDoneThisWeek ?? 0) > 0)
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                    <span class="font-semibold">{{ $notDoneThisWeek }}</span> Not Done mark(s) this week.
                </div>
            @endif

            @if ($checks->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No Done / Not Done marks recorded for this student yet.</p>
            @else
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[36rem] text-left text-sm">
                            <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Subject</th>
                                    <th class="px-4 py-2">Topic</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">WhatsApp</th>
                                    <th class="px-4 py-2">Marked by</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($checks as $check)
                                    @php
                                        $isNotDone = $check->status === \App\Enums\HomeworkCheckStatus::NotDone;
                                    @endphp
                                    <tr wire:key="hw-check-{{ $check->id }}">
                                        <td class="px-4 py-2 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                            {{ $check->checked_on?->format('d M Y') ?? $check->created_at?->format('d M Y H:i') }}
                                        </td>
                                        <td class="px-4 py-2 font-medium text-gray-950 dark:text-white">
                                            {{ $check->subject_name }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                            {{ \Illuminate\Support\Str::limit($check->topic, 48) }}
                                            @if ($check->homeworkAssignment)
                                                <span class="mt-0.5 block text-[11px] text-primary-600 dark:text-primary-400">
                                                    Linked: {{ $check->homeworkAssignment->title }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $isNotDone,
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => ! $isNotDone,
                                            ])>
                                                {{ $check->status?->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $check->notify_status?->label() ?? '—' }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">
                                            {{ $check->createdBy?->name ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-3">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Assigned homework</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Portal homework shared with this student&apos;s batch.</p>
            </div>

            @if ($assignments->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">No homework assigned to this student&apos;s batch yet.</p>
            @else
                <div class="space-y-3">
                    @foreach ($assignments as $assignment)
                        @php
                            $viewed = $assignment->views->isNotEmpty();
                        @endphp
                        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $assignment->title }}</p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $assignment->batch?->name }} · {{ $assignment->published_at?->format('d M Y') }}
                                    </p>
                                </div>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $viewed ? 'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' }}">
                                    {{ $viewed ? 'Viewed in portal' : 'Not viewed' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200 line-clamp-3">{{ $assignment->description }}</p>
                        </div>
                    @endforeach
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Students open homework in the portal:
                        <a href="{{ $portalUrl }}" target="_blank" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $portalUrl }}</a>
                    </p>
                </div>
            @endif
        </div>
    </div>
@endif
