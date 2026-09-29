@php
    $canTransfer = $caseService->canTransfer($case, $viewer);
    $canClose = $caseService->canClose($case, $viewer);
    $canLogCall = $caseService->canLogCall($case, $viewer);
    $canAddUpdate = $caseService->canAddUpdate($case, $viewer);
    $canEditDetails = $caseService->canEditDetails($case, $viewer);
    $canRequestEdit = $caseService->canRequestEdit($case, $viewer);
    $canReviewRevision = $caseService->canReviewRevision($case, $viewer);
    $canReopen = $caseService->canReopen($case, $viewer);
    $earlierClosingNote = $case->notes->where('kind', \App\Models\StudentCaseNote::KIND_REOPEN)->sortByDesc('id')->first()?->body;
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

            @if ($canEditDetails)
                @if (($showEditCaseForm ?? false) && ($expandedCaseId ?? null) === $case->id)
                    <form wire:submit="submitEditCase({{ $case->id }})" class="mt-4 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">Edit case</p>
                            <button type="button" wire:click="cancelEditCase" class="text-xs font-semibold text-gray-500">Cancel</button>
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Title</label>
                            <input type="text" wire:model="editCaseTitle" required class="fi-crm-input mt-1 block w-full" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">What happened</label>
                            <textarea wire:model="editCaseSummary" rows="3" class="fi-crm-input mt-1 block w-full"></textarea>
                        </div>
                        @if (! $case->isOpen())
                            <div>
                                <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Closing note</label>
                                <textarea wire:model="editCaseClosingNote" rows="2" required class="fi-crm-input mt-1 block w-full"></textarea>
                            </div>
                        @endif
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="inline-flex rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                                Save now
                            </button>
                            @if ($canRequestEdit)
                                <button type="button" wire:click="submitCaseEditRequest({{ $case->id }})" class="inline-flex rounded-lg bg-white px-3 py-2 text-sm font-semibold text-gray-800 ring-1 ring-gray-300 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-100 dark:ring-white/15">
                                    Ask admin to approve
                                </button>
                            @endif
                        </div>
                    </form>
                @else
                    <button
                        type="button"
                        wire:click="startEditCase({{ $case->id }})"
                        class="mt-3 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Edit case
                    </button>
                @endif
            @endif

            @if ($case->isOpen() && ! $isAssignee && $case->currentAssignee)
                <div class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20">
                    @if ($isAdminReassign)
                        This case is with <strong>{{ $case->currentAssignee->name }}</strong>. You can correct the story, approve an edit, or close it if the reopen was a mistake. Only they can log calls.
                    @else
                        This case is assigned to <strong>{{ $case->currentAssignee->name }}</strong>. Only they can add a meeting note, log a call, transfer, or close it.
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
                                    'bg-amber-500' => $item['type'] === 'note' || $item['type'] === 'revision',
                                    'bg-gray-500' => $item['type'] === 'closed',
                                ])></span>

                                <div @class([
                                    'rounded-xl px-3 py-2.5 ring-1',
                                    'bg-violet-50/80 ring-violet-200/70 dark:bg-violet-500/5 dark:ring-violet-500/20' => $item['type'] === 'assignment',
                                    'bg-sky-50/80 ring-sky-200/70 dark:bg-sky-500/5 dark:ring-sky-500/20' => $item['type'] === 'call',
                                    'bg-amber-50/80 ring-amber-200/70 dark:bg-amber-500/5 dark:ring-amber-500/20' => $item['type'] === 'note' || $item['type'] === 'revision',
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

                                    @php
                                        $trailNote = ($item['note_id'] ?? null)
                                            ? $case->notes->firstWhere('id', $item['note_id'])
                                            : null;
                                        $canEditThisNote = $trailNote && $caseService->canEditNote($case, $trailNote, $viewer);
                                    @endphp

                                    @if ($canEditThisNote && ($editingCaseNoteId ?? null) === $trailNote->id)
                                        <form wire:submit="submitCaseNoteEdit({{ $case->id }})" class="mt-2 space-y-2">
                                            <textarea wire:model="editingCaseNoteBody" rows="3" required class="fi-crm-input block w-full"></textarea>
                                            <div class="flex gap-2">
                                                <button type="submit" class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white">Save</button>
                                                <button type="button" wire:click="cancelCaseNoteEdit" class="text-xs font-semibold text-gray-500">Cancel</button>
                                            </div>
                                        </form>
                                    @else
                                        @if ($item['summary'])
                                            <p class="mt-1 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item['summary'] }}</p>
                                        @endif
                                        @if ($canEditThisNote)
                                            <button
                                                type="button"
                                                wire:click="startCaseNoteEdit({{ $case->id }}, {{ $trailNote->id }})"
                                                class="mt-2 text-xs font-semibold text-primary-600 dark:text-primary-400"
                                            >
                                                Edit
                                            </button>
                                        @endif
                                    @endif

                                    @foreach ($item['changes'] ?? [] as $change)
                                        <div class="mt-2 space-y-1 text-sm">
                                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $change['label'] }}</p>
                                            <p class="text-gray-600 dark:text-gray-300"><span class="font-semibold">Old:</span> {{ filled($change['old']) ? $change['old'] : '—' }}</p>
                                            <p class="text-gray-950 dark:text-white"><span class="font-semibold">Updated:</span> {{ filled($change['new']) ? $change['new'] : '—' }}</p>
                                        </div>
                                    @endforeach

                                    @if ($canReviewRevision && ($item['revision_status'] ?? null) === 'pending' && ($item['revision_id'] ?? null))
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <button type="button" wire:click="acceptCaseRevision({{ $case->id }}, {{ $item['revision_id'] }})" class="inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white">
                                                Approve edit
                                            </button>
                                            <button type="button" wire:click="rejectCaseRevision({{ $case->id }}, {{ $item['revision_id'] }})" class="inline-flex rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-gray-800 ring-1 ring-gray-300 dark:bg-white/5 dark:text-gray-100 dark:ring-white/15">
                                                Reject
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>

            @php
                $openPanel = ($expandedCaseId ?? null) === $case->id ? ($caseActionPanel ?? '') : '';
            @endphp

            @if ($canReopen)
                <form wire:submit="submitCaseReopen({{ $case->id }})" class="mt-4">
                    <button type="submit" wire:confirm="Reopen this case?" class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                        Reopen case
                    </button>
                </form>
            @endif

            @if ($canAddUpdate || ($case->isOpen() && ($canLogCall || $canTransfer || $canClose)))
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @if ($canAddUpdate)
                        <button
                            type="button"
                            wire:click="openCaseAction({{ $case->id }}, 'note')"
                            @class([
                                'inline-flex min-h-11 items-center justify-center rounded-xl px-3 py-2.5 text-sm font-semibold',
                                'bg-amber-600 text-white' => $openPanel === 'note',
                                'bg-amber-50 text-amber-900 ring-1 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-100 dark:ring-amber-500/30' => $openPanel !== 'note',
                            ])
                        >
                            Add meeting note
                        </button>
                    @endif
                    @if ($case->isOpen() && $canLogCall)
                        <button
                            type="button"
                            wire:click="openLogCallForCase({{ $case->id }})"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-50 px-3 py-2.5 text-sm font-semibold text-emerald-900 ring-1 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-100 dark:ring-emerald-500/30"
                        >
                            Log a call
                        </button>
                    @endif
                    @if ($case->isOpen() && $canTransfer)
                        <button
                            type="button"
                            wire:click="openCaseAction({{ $case->id }}, 'transfer')"
                            @class([
                                'inline-flex min-h-11 items-center justify-center rounded-xl px-3 py-2.5 text-sm font-semibold',
                                'bg-primary-600 text-white' => $openPanel === 'transfer',
                                'bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-100 dark:ring-white/15' => $openPanel !== 'transfer',
                            ])
                        >
                            Transfer case
                        </button>
                    @endif
                    @if ($case->isOpen() && $canClose)
                        <button
                            type="button"
                            wire:click="openCaseAction({{ $case->id }}, 'close')"
                            @class([
                                'inline-flex min-h-11 items-center justify-center rounded-xl px-3 py-2.5 text-sm font-semibold',
                                'bg-gray-900 text-white dark:bg-white dark:text-gray-900' => $openPanel === 'close',
                                'bg-white text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-100 dark:ring-white/15' => $openPanel !== 'close',
                            ])
                        >
                            Close case
                        </button>
                    @endif
                </div>
            @endif

            @if ($openPanel === 'note')
                <form wire:submit="submitCaseUpdate({{ $case->id }})" class="mt-3 space-y-3 rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Add meeting note</p>
                        <button type="button" wire:click="openCaseAction({{ $case->id }}, 'note')" class="text-xs font-semibold text-gray-500">Cancel</button>
                    </div>
                    <textarea wire:model="caseUpdateBody" rows="3" required class="fi-crm-input block w-full" placeholder="Write the meeting note"></textarea>
                    <button type="submit" class="inline-flex min-h-11 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-500">
                        Save
                    </button>
                </form>
            @endif

            @if ($openPanel === 'transfer')
                <form wire:submit="submitCaseTransfer({{ $case->id }})" class="mt-3 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Transfer case</p>
                        <button type="button" wire:click="openCaseAction({{ $case->id }}, 'transfer')" class="text-xs font-semibold text-gray-500">Cancel</button>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Staff</label>
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
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Why</label>
                        <textarea wire:model="caseTransferNote" rows="2" required class="fi-crm-input mt-1 block w-full" placeholder="Why is this case being transferred?"></textarea>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-500">
                        Save
                    </button>
                </form>
            @endif

            @if ($openPanel === 'close')
                <form wire:submit="submitCaseClose({{ $case->id }})" class="mt-3 space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">Close case</p>
                        <button type="button" wire:click="openCaseAction({{ $case->id }}, 'close')" class="text-xs font-semibold text-gray-500">Cancel</button>
                    </div>
                    <textarea wire:model="caseClosingNote" rows="3" required class="fi-crm-input block w-full" placeholder="How was this finished?"></textarea>
                    @if (filled($earlierClosingNote))
                        <button type="button" wire:click="fillEarlierClosingNote({{ $case->id }})" class="text-xs font-semibold text-primary-600 dark:text-primary-400">
                            Use the earlier closing note
                        </button>
                    @endif
                    <button type="submit" class="inline-flex min-h-11 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900">
                        Close case
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
