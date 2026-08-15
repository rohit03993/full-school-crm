@php
    $canTransfer = $caseService->canTransfer($case, $viewer);
    $canClose = $caseService->canClose($case, $viewer);
    $canLogCall = $caseService->canLogCall($case, $viewer);
    $isAssignee = $caseService->isCurrentAssignee($case, $viewer);
    $canReassignAsAdmin = $caseService->canReassignAsAdmin($case, $viewer);
    $isAdminReassign = $canReassignAsAdmin && ! $isAssignee;
    $trail = $caseService->activityTrail($case);
@endphp

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    {{-- Chips sit under the title, never beside the toggle: on a phone they collided with it --}}
    <button
        type="button"
        wire:click="toggleCase({{ $case->id }})"
        class="flex w-full touch-manipulation items-start gap-3 px-4 py-4 text-left sm:px-6"
    >
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="break-all font-mono text-[11px] font-bold text-primary-600 dark:text-primary-400">{{ $case->case_number }}</span>
                <x-crm.badge :tone="$case->isOpen() ? 'success' : 'gray'">{{ $case->status->label() }}</x-crm.badge>
            </div>

            <p class="mt-1.5 text-sm font-semibold text-gray-950 dark:text-white">{{ $case->title }}</p>

            <div class="mt-2 flex flex-wrap gap-1.5">
                <x-crm.badge tone="gray">{{ $case->case_type->label() }}</x-crm.badge>
                @if ($case->isOpen() && $case->currentAssignee)
                    <span class="crm-badge bg-violet-100 text-violet-800 ring-violet-500/20 dark:bg-violet-500/15 dark:text-violet-300">
                        With {{ $case->currentAssignee->name }}
                    </span>
                @endif
            </div>

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Opened {{ $case->opened_at?->format('d M Y, h:i A') }} by {{ $case->openedBy?->name ?? 'Staff' }}
            </p>
        </div>

        <span class="flex shrink-0 items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400">
            <span class="hidden sm:inline">{{ $expanded ? 'Hide' : 'Details' }}</span>
            <span class="sr-only sm:hidden">{{ $expanded ? 'Hide case details' : 'Show case details' }}</span>
            <svg @class(['h-5 w-5 transition', 'rotate-180' => $expanded]) fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </span>
    </button>

    @if ($expanded)
        <div class="border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            @if ($case->summary)
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $case->summary }}</p>
            @endif

            @if ($case->isOpen() && ! $isAssignee && $case->currentAssignee)
                <div class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20">
                    @if ($isAdminReassign)
                        This case is with <strong>{{ $case->currentAssignee->name }}</strong>. As Super Admin you can reassign it below; only the current assignee can log calls or close it.
                    @else
                        This case is assigned to <strong>{{ $case->currentAssignee->name }}</strong>. Only they can log calls, transfer, or close it.
                    @endif
                </div>
            @endif

            <div class="mt-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Case trail</h4>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Assignments, calls, and closure in order.</p>

                @if ($trail->isEmpty())
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No activity recorded yet.</p>
                @else
                    <ol class="relative mt-4 space-y-0 border-l-2 border-gray-200 pl-5 dark:border-white/10">
                        @foreach ($trail as $item)
                            <li class="relative pb-5 last:pb-0">
                                <span @class([
                                    'absolute -left-[1.35rem] top-1 flex h-3 w-3 rounded-full ring-2 ring-white dark:ring-gray-900',
                                    'bg-violet-500' => $item['type'] === 'assignment',
                                    'bg-sky-500' => $item['type'] === 'call',
                                    'bg-gray-500' => $item['type'] === 'closed',
                                ])></span>

                                <div @class([
                                    'rounded-xl px-3 py-2.5 ring-1',
                                    'bg-violet-50/80 ring-violet-200/70 dark:bg-violet-500/5 dark:ring-violet-500/20' => $item['type'] === 'assignment',
                                    'bg-sky-50/80 ring-sky-200/70 dark:bg-sky-500/5 dark:ring-sky-500/20' => $item['type'] === 'call',
                                    'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => $item['type'] === 'closed',
                                ])>
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                                {{ $item['occurred_at']->format('d M Y, h:i A') }}
                                                @if ($item['actor_name'])
                                                    · {{ $item['actor_name'] }}
                                                @endif
                                            </p>
                                        </div>
                                        @if ($item['status_label'])
                                            <span class="shrink-0 rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-black/20 dark:text-gray-200">
                                                {{ $item['status_label'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($item['detail'])
                                        <p class="mt-1 text-xs font-medium text-gray-600 dark:text-gray-300">{{ $item['detail'] }}</p>
                                    @endif

                                    @if ($item['summary'])
                                        <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item['summary'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @if ($case->isOpen() && $canLogCall)
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="openLogCallForCase({{ $case->id }})"
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                        Log call on case
                    </button>
                </div>
            @endif

            @if ($case->isOpen() && $canTransfer)
                <form wire:submit="submitCaseTransfer({{ $case->id }})" class="mt-4 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $isAdminReassign ? 'Reassign case (admin)' : 'Transfer case' }}
                    </p>
                    <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Assign to</label>
                        <x-crm.select wire:model="caseTransferAssigneeId" class="mt-1" required>
                            <option value="">Select staff…</option>
                            @foreach ($staffOptions as $id => $name)
                                @if ((int) $id !== (int) $case->current_assignee_user_id)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endif
                            @endforeach
                        </x-crm.select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">{{ $isAdminReassign ? 'Reassignment note' : 'Transfer note' }}</label>
                        <textarea wire:model="caseTransferNote" rows="2" required class="fi-crm-input mt-1 block w-full" placeholder="{{ $isAdminReassign ? 'Why is admin reassigning this case?' : 'Why is this being reassigned?' }}"></textarea>
                    </div>
                    <button type="submit" class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                        {{ $isAdminReassign ? 'Reassign' : 'Transfer' }}
                    </button>
                </form>
            @endif

            @if ($case->isOpen() && $canClose)
                <form wire:submit="submitCaseClose({{ $case->id }})" class="mt-4 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Close case</p>
                    <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Closing note</label>
                        <textarea wire:model="caseClosingNote" rows="2" required class="fi-crm-input mt-1 block w-full" placeholder="Final resolution and outcome"></textarea>
                    </div>
                    <button type="submit" class="inline-flex rounded-lg bg-gray-800 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-900">
                        Close case
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
