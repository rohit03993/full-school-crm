@php
    $canTransfer = $caseService->canTransfer($case, $viewer);
    $canClose = $caseService->canClose($case, $viewer);
@endphp

<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <button
        type="button"
        wire:click="toggleCase({{ $case->id }})"
        class="flex w-full items-start justify-between gap-3 px-4 py-4 text-left sm:px-6"
    >
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-bold text-primary-600 dark:text-primary-400">{{ $case->case_number }}</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $case->isOpen(),
                    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $case->isOpen(),
                ])>
                    {{ $case->status->label() }}
                </span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                    {{ $case->case_type->label() }}
                </span>
            </div>
            <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $case->title }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Opened {{ $case->opened_at?->format('d M Y, h:i A') }} by {{ $case->openedBy?->name ?? 'Staff' }}
                @if ($case->currentAssignee)
                    · Assigned to {{ $case->currentAssignee->name }}
                @endif
            </p>
        </div>
        <span class="shrink-0 text-xs font-semibold text-primary-600 dark:text-primary-400">
            {{ $expanded ? 'Hide' : 'Details' }}
        </span>
    </button>

    @if ($expanded)
        <div class="border-t border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            @if ($case->summary)
                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $case->summary }}</p>
            @endif

            <div class="mt-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Assignment trail</h4>
                <ol class="mt-2 space-y-2">
                    @foreach ($case->assignments as $assignment)
                        <li class="rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                            <p class="font-medium text-gray-950 dark:text-white">
                                @if ($assignment->fromUser)
                                    {{ $assignment->fromUser->name }} → {{ $assignment->toUser->name }}
                                @else
                                    Opened → {{ $assignment->toUser->name }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                By {{ $assignment->assignedBy?->name ?? 'Staff' }}
                                · {{ $assignment->created_at?->format('d M Y, h:i A') }}
                            </p>
                            @if ($assignment->note)
                                <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $assignment->note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>

            @if ($case->calls->isNotEmpty())
                <div class="mt-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Calls on this case</h4>
                    <div class="mt-2 space-y-2">
                        @foreach ($case->calls as $call)
                            <div class="rounded-lg bg-sky-50 px-3 py-2 text-sm dark:bg-sky-500/10">
                                <p class="font-medium text-sky-900 dark:text-sky-200">
                                    {{ $call->called_at?->format('d M Y, h:i A') }}
                                    · {{ $call->call_status->label() }}
                                    · {{ $call->staff?->name }}
                                </p>
                                @if ($call->call_notes)
                                    <p class="mt-1 text-xs leading-relaxed text-gray-700 dark:text-gray-300">{{ $call->call_notes }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($case->isOpen())
                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="openLogCallForCase({{ $case->id }})"
                        class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                    >
                        Log call on case
                    </button>
                </div>

                @if ($canTransfer)
                    <form wire:submit="submitCaseTransfer({{ $case->id }})" class="mt-4 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Transfer case</p>
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Assign to</label>
                            <x-crm.select wire:model="caseTransferAssigneeId" class="mt-1" required>
                                <option value="">Select staff…</option>
                                @foreach ($staffOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-crm.select>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Transfer note</label>
                            <textarea wire:model="caseTransferNote" rows="2" required class="fi-crm-input mt-1 block w-full" placeholder="Why is this being reassigned?"></textarea>
                        </div>
                        <button type="submit" class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                            Transfer
                        </button>
                    </form>
                @endif

                @if ($canClose)
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
            @else
                <div class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Closed</p>
                    <p class="mt-1 text-gray-700 dark:text-gray-300">
                        {{ $case->closed_at?->format('d M Y, h:i A') }}
                        by {{ $case->closedBy?->name ?? 'Staff' }}
                    </p>
                    @if ($case->closing_note)
                        <p class="mt-1 text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $case->closing_note }}</p>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
