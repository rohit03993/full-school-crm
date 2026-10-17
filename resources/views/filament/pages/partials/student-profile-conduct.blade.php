<div wire:init="loadConductTab" class="space-y-4">
    <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">New report</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Saved on this student only. It does not create a fee or a receipt.</p>

        @if ($conductTypeOptions === [])
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No report types are switched on. Super Admin can add them under Setup → Report types.</p>
        @else
            <div class="fi-crm-form mt-4 grid gap-4">
                <div>
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Date</label>
                    <input type="date" wire:model="conductRecordedOn" max="{{ now()->toDateString() }}" class="fi-crm-input mt-2 block min-h-11 w-full" />
                </div>

                <div>
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Types</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tick one or more.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        @foreach ($conductTypeOptions as $value => $label)
                            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-800 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 dark:border-white/10 dark:text-gray-100 dark:has-[:checked]:border-rose-400/60 dark:has-[:checked]:bg-rose-500/10">
                                <input type="checkbox" value="{{ $value }}" wire:model="conductTypeKeys" class="h-4 w-4 rounded border-gray-300 text-rose-600" />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Fine</label>
                        <x-crm.select wire:model.live="conductFineChoice" class="mt-2 min-h-11">
                            <option value="without_fine">Without fine</option>
                            <option value="fine_charged">Fine charged</option>
                        </x-crm.select>
                    </div>
                    @if ($conductFineChoice === 'fine_charged')
                        <div>
                            <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Amount (₹)</label>
                            <input type="number" min="1" step="1" inputmode="numeric" wire:model="conductAmount" class="fi-crm-input mt-2 block min-h-11 w-full" placeholder="e.g. 200" />
                        </div>
                    @endif
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">What happened</label>
                    <textarea wire:model="conductNote" rows="3" maxlength="1000" class="fi-crm-input mt-2 block min-h-24 w-full" placeholder="Short note about this incident"></textarea>
                </div>

                <button
                    type="button"
                    wire:click="saveConductRecord"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white hover:bg-rose-500 sm:w-auto"
                >
                    Save report
                </button>
            </div>
        @endif
    </div>

    <div class="flex items-end justify-between gap-3">
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Saved reports</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">Newest first</p>
        </div>
        @if (($reportCount ?? 0) > 0)
            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800 dark:bg-rose-500/15 dark:text-rose-200">{{ $reportCount }}</span>
        @endif
    </div>

    @if (! $conductTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading reports…</p>
    @elseif ($conductRecords->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center dark:border-white/20 dark:bg-gray-900">
            <p class="text-sm text-gray-500 dark:text-gray-400">No reports for this student yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($conductRecords as $conductRecord)
                <article class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $conductRecord->typeLabelList() }}</p>
                        <span @class([
                            'rounded-full px-2.5 py-1 text-xs font-semibold',
                            'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200' => $conductRecord->fine_charged,
                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! $conductRecord->fine_charged,
                        ])>{{ $conductRecord->fineSummary() }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $conductRecord->note }}</p>
                    <p class="mt-3 text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $conductRecord->recorded_on?->format('d M Y') }}
                        · Entered by {{ $conductRecord->recordedBy?->name ?: 'Unknown' }}
                    </p>
                    @if ($canDeleteConduct)
                        <button
                            type="button"
                            wire:click="deleteConductRecord({{ $conductRecord->id }})"
                            wire:confirm="Remove this report?"
                            class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-rose-700 hover:underline dark:text-rose-300"
                        >
                            Remove
                        </button>
                    @endif
                </article>
            @endforeach
        </div>
        @if (($reportCount ?? 0) > $conductRecords->count())
            <p class="text-center text-xs text-gray-500 dark:text-gray-400">
                Showing the latest {{ $conductRecords->count() }}.
                <a href="{{ \App\Filament\Pages\StudentReportsPage::getUrl() }}" class="font-semibold text-rose-700 hover:underline dark:text-rose-300">All student reports</a>
            </p>
        @endif
    @endif
</div>
