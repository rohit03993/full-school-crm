<div wire:init="loadCallsTab">
    @if (! $callsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading calls…</p>
    @elseif ($calls->isEmpty())
        <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No calls logged yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($calls as $call)
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $call->call_status->label() }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $call->called_at?->format('d M Y, h:i A') }}
                                · {{ $call->call_direction->label() }}
                                @if ($call->staff)
                                    · {{ $call->staff->name }}
                                @endif
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                        @if ($call->visit_status_changed_to)
                            <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ $call->visit_status_changed_to->label() }}
                            </span>
                        @endif
                        @if ($call->whatsapp_auto_status)
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                                'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $call->whatsapp_auto_status->value === 'success',
                                'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300' => $call->whatsapp_auto_status->value === 'queued',
                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $call->whatsapp_auto_status->value === 'skipped',
                                'bg-danger-100 text-danger-800 dark:bg-danger-500/15 dark:text-danger-300' => $call->whatsapp_auto_status->value === 'failed',
                            ])>
                                {{ $call->whatsapp_auto_status->label() }}
                            </span>
                        @endif
                        </div>
                    </div>

                    @if ($call->who_answered)
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Answered by: {{ $call->who_answered->label() }}</p>
                    @endif

                    @if ($call->call_notes)
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $call->call_notes }}</p>
                    @endif

                    @if ($call->next_followup_at)
                        <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                            Follow-up: {{ $call->next_followup_at->format('d M Y, h:i A') }}
                        </p>
                    @endif

                    @if (filled($call->tags))
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach ($call->tags as $tag)
                                <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
