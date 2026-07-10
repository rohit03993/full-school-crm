@php
    use App\Enums\CallDirection;
    use App\Enums\CallQuickTag;
    use App\Enums\CallStatus;
    use App\Enums\VisitStatus;
    use App\Enums\WhoAnswered;
@endphp

<div class="mx-auto max-w-lg space-y-4 pb-24 lg:max-w-2xl lg:pb-6">
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        @foreach ([
            ['label' => 'Calls today', 'value' => $stats['calls_today'] ?? 0],
            ['label' => 'Connected', 'value' => $stats['connected_today'] ?? 0],
            ['label' => 'Due now', 'value' => $stats['queue_count'] ?? 0],
            ['label' => 'Scheduled', 'value' => $stats['scheduled_count'] ?? 0],
            ['label' => 'Overdue / due', 'value' => $stats['pending_followups'] ?? 0],
        ] as $stat)
            <div class="rounded-xl bg-white px-3 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if (! $currentLead)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-lg font-semibold text-gray-950 dark:text-white">All done for today</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No assigned leads are due for calling right now.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 bg-gradient-to-r from-primary-500/10 to-transparent px-4 py-3 dark:border-white/10 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Due now</p>
                @if ($currentLead['is_overdue'])
                    <span class="mt-1 inline-flex rounded-full bg-danger-100 px-2 py-0.5 text-[10px] font-bold uppercase text-danger-700 dark:bg-danger-500/15 dark:text-danger-300">Overdue</span>
                @elseif ($currentLead['is_due_today'])
                    <span class="mt-1 inline-flex rounded-full bg-warning-100 px-2 py-0.5 text-[10px] font-bold uppercase text-warning-800 dark:bg-warning-500/15 dark:text-warning-300">Due today</span>
                @endif
            </div>

            <div class="space-y-4 px-4 py-5 sm:px-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-950 dark:text-white">{{ $currentLead['name'] }}</h2>
                    @if ($currentLead['father_name'])
                        <p class="text-sm text-gray-500 dark:text-gray-400">Parent: {{ $currentLead['father_name'] }}</p>
                    @endif
                    <p class="mt-2 font-mono text-lg font-bold text-primary-600 dark:text-primary-400">{{ $currentLead['mobile_display'] }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $currentLead['course'] }} · {{ $currentLead['status_label'] }}</p>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Total calls</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $currentLead['total_calls'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">Failed tries</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $currentLead['not_connected_attempts_count'] }}/3</dd>
                    </div>
                    @if ($currentLead['last_call_at'])
                        <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Last call</dt>
                            <dd class="font-medium text-gray-950 dark:text-white">{{ $currentLead['last_call_at'] }}</dd>
                        </div>
                    @endif
                    @if ($currentLead['assigned_by_name'])
                        <div class="col-span-2 rounded-lg bg-sky-50 px-3 py-2 dark:bg-sky-500/10">
                            <dt class="text-xs text-sky-700 dark:text-sky-300">Assigned for calling</dt>
                            <dd class="font-medium text-sky-900 dark:text-sky-200">
                                By {{ $currentLead['assigned_by_name'] }}
                                @if ($currentLead['assigned_at_label'])
                                    · {{ $currentLead['assigned_at_label'] }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($currentLead['calling_handoff_note'])
                        <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Handoff note</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ $currentLead['calling_handoff_note'] }}</dd>
                        </div>
                    @endif
                    @if ($currentLead['follow_up_queue_label'])
                        <div class="col-span-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">
                            <dt class="text-xs text-amber-700 dark:text-amber-300">Queue status</dt>
                            <dd class="font-medium text-amber-900 dark:text-amber-200">{{ $currentLead['follow_up_queue_label'] }}</dd>
                        </div>
                    @endif
                    @if ($currentLead['next_followup_at'])
                        <div class="col-span-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">
                            <dt class="text-xs text-amber-700 dark:text-amber-300">Follow-up</dt>
                            <dd class="font-medium text-amber-900 dark:text-amber-200">{{ $currentLead['next_followup_at'] }}</dd>
                        </div>
                    @endif
                    @if ($currentLead['last_call_notes'])
                        <div class="col-span-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Last notes</dt>
                            <dd class="text-gray-700 dark:text-gray-300">{{ $currentLead['last_call_notes'] }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="flex flex-col gap-2 sm:flex-row">
                    @if ($currentLead['mobile_raw'])
                        <button
                            type="button"
                            onclick="window.CrmPendingCall.start({{ $currentLead['id'] }}, @js($currentLead['name']), @js($currentLead['mobile_display']), @js('tel:+91'.substr($currentLead['mobile_raw'], -10)), {{ (int) ($currentLead['not_connected_attempts_count'] ?? 0) }})"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3.5 text-base font-bold text-white shadow-sm hover:bg-emerald-500"
                        >
                            Call Now
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="openLogCallModal"
                        class="inline-flex flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-3.5 text-base font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        Log call result
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tap <strong>Call Now</strong> to open your phone dialer. When you return to the CRM, the log form opens automatically.</p>

                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="skipLead" class="text-sm font-semibold text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-white">
                        Skip to next →
                    </button>
                    <a href="{{ $currentLead['profile_url'] }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        Open full profile
                    </a>
                </div>
            </div>
        </div>
    @endif

    @if (($scheduledLeads ?? collect())->isNotEmpty())
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-300">Scheduled follow-ups</p>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">These appear in your due queue on the follow-up date.</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($scheduledLeads as $lead)
                    <div class="flex items-start justify-between gap-3 px-4 py-3 sm:px-6">
                        <div>
                            <a href="{{ $lead['profile_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ $lead['name'] }}</a>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $lead['course'] }} · {{ $lead['mobile_display'] }}</p>
                            <p class="mt-1 text-xs font-medium text-sky-700 dark:text-sky-300">{{ $lead['follow_up_queue_label'] }}</p>
                        </div>
                        <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                            {{ $lead['next_followup_date'] }}<br>
                            {{ $lead['next_followup_time'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @include('filament.pages.partials.log-call-modal', [
        'showLogCallModal' => $showLogCallModal,
        'logCallForm' => $logCallForm,
        'logCallModalMode' => $logCallModalMode ?? 'queue',
        'logCallLeadName' => $logCallLeadName ?? null,
        'logCallLeadPhone' => $logCallLeadPhone ?? null,
    ])
</div>
