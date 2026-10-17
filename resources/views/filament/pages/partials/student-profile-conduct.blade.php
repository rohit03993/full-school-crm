<div wire:init="loadConductTab" class="space-y-4">
    <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-5">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Add conduct record</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This note stays on the student profile. It does not create a fee or a receipt.</p>

        @if ($conductTypeOptions === [])
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No conduct types are switched on. Super Admin can add them under Setup → Conduct types.</p>
        @else
            <div class="fi-crm-form mt-3 grid gap-3">
                <div class="sm:max-w-xs">
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Date</label>
                    <input type="date" wire:model="conductRecordedOn" max="{{ now()->toDateString() }}" class="fi-crm-input mt-2 block w-full" />
                </div>

                <div>
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400">Types</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tick one or more.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($conductTypeOptions as $value => $label)
                            <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input type="checkbox" value="{{ $value }}" wire:model="conductTypeKeys" class="rounded border-gray-300 text-primary-600" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Fine</label>
                        <x-crm.select wire:model.live="conductFineChoice" class="mt-2">
                            <option value="without_fine">Without fine</option>
                            <option value="fine_charged">Fine charged</option>
                        </x-crm.select>
                    </div>
                    @if ($conductFineChoice === 'fine_charged')
                        <div>
                            <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">Amount (₹)</label>
                            <input type="number" min="1" step="1" inputmode="numeric" wire:model="conductAmount" class="fi-crm-input mt-2 block w-full" placeholder="e.g. 200" />
                        </div>
                    @endif
                </div>

                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-400">What happened</label>
                    <textarea wire:model="conductNote" rows="3" maxlength="1000" class="fi-crm-input mt-2 block w-full" placeholder="Short note about this incident"></textarea>
                </div>

                <div>
                    <button
                        type="button"
                        wire:click="saveConductRecord"
                        class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                    >
                        Save record
                    </button>
                </div>
            </div>
        @endif
    </div>

    @if (! $conductTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading conduct records…</p>
    @elseif ($conductRecords->isEmpty())
        <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
            <p class="text-sm text-gray-500 dark:text-gray-400">No conduct records for this student yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($conductRecords as $conductRecord)
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $conductRecord->typeLabelList() }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">Entered by {{ $conductRecord->recordedBy?->name ?: 'Unknown' }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $conductRecord->recorded_on?->format('d M Y') }}
                                · {{ $conductRecord->fineSummary() }}
                            </p>
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $conductRecord->note }}</p>
                        </div>
                        @if ($canDeleteConduct)
                            <button
                                type="button"
                                wire:click="deleteConductRecord({{ $conductRecord->id }})"
                                wire:confirm="Remove this conduct record?"
                                class="text-sm font-semibold text-danger-600 hover:underline dark:text-danger-400"
                            >
                                Remove
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
