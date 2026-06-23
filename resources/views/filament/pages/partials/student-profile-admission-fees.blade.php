@if ($activeAdmission->course_fee !== null)
    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Course fee</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) $activeAdmission->course_fee, 2) }}</dd>
        </div>

        @if ($activeAdmission->canAdjustFees())
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Discount (₹)</dt>
                <dd>
                    @if ($this->canManageAdmissionFeePlan)
                        <x-filament::input.wrapper>
                            <x-filament::input
                                type="number"
                                wire:model.live="discountAmount"
                                step="0.01"
                                min="0"
                            />
                        </x-filament::input.wrapper>
                        @error('discountAmount')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                    @else
                        <span class="font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) ($activeAdmission->discount_amount ?? 0), 2) }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Net fee</dt>
                <dd class="font-bold text-primary-600 dark:text-primary-400">₹{{ $this->admissionNetFee }}</dd>
            </div>
        @else
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Discount</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) ($activeAdmission->discount_amount ?? 0), 2) }}</dd>
                @if ((float) ($activeAdmission->discount_amount ?? 0) > 0 && $activeAdmission->discountSetBy)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Granted by {{ $activeAdmission->discountSetBy->name }}</p>
                @endif
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Net fee</dt>
                <dd class="font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) $activeAdmission->net_fee, 2) }}</dd>
            </div>
        @endif
    </dl>

    @if ($activeAdmission->canAdjustFees() && $this->canManageAdmissionFeePlan)
        @if ($this->admissionCourseFeeZero)
            <div class="mt-3 rounded-lg border border-danger-500/30 bg-danger-500/5 px-3 py-2 text-xs text-danger-700 dark:text-danger-300">
                This course has ₹0 fee. Set the fee in Courses admin before saving a fee plan.
            </div>
        @endif

        <div class="mt-4 space-y-4 rounded-xl border border-gray-200 px-4 py-4 dark:border-white/10 sm:px-5">
            <div>
                <div class="flex items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">Miscellaneous charges</h4>
                    <x-filament::button type="button" wire:click="addMiscFeeRow" size="xs" color="gray">Add charge</x-filament::button>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Optional extras (transport, exam fee, etc.) added to the net fee.</p>
                @forelse ($this->miscFees as $index => $miscFee)
                    <div class="mt-3 grid gap-2 sm:grid-cols-[1fr_140px_auto]" wire:key="misc-fee-{{ $index }}">
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model.live="miscFees.{{ $index }}.label" placeholder="Label" />
                        </x-filament::input.wrapper>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" wire:model.live="miscFees.{{ $index }}.amount" step="0.01" min="0" placeholder="Amount" />
                        </x-filament::input.wrapper>
                        <x-filament::button type="button" wire:click="removeMiscFeeRow({{ $index }})" size="sm" color="danger" outlined>Remove</x-filament::button>
                    </div>
                @empty
                    <p class="mt-2 text-xs text-gray-400">No miscellaneous charges.</p>
                @endforelse
            </div>

            <div class="border-t border-gray-100 pt-4 dark:border-white/10">
                <label class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                    <input type="checkbox" wire:model.live="useInstallmentPlan" class="rounded border-gray-300 text-primary-600" />
                    Split into installments
                </label>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">When off, full net fee is due as one installment after approval.</p>

                @if ($this->useInstallmentPlan)
                    <div class="mt-3 rounded-lg border border-primary-500/30 bg-primary-500/5 px-3 py-2 text-xs text-primary-900 dark:text-primary-200">
                        {{ $this->admissionInstallmentSummary }}
                    </div>
                    @if ($this->admissionInstallmentWarning)
                        <div class="mt-2 rounded-lg border border-danger-500/30 bg-danger-500/5 px-3 py-2 text-xs text-danger-700 dark:text-danger-300">
                            {{ $this->admissionInstallmentWarning }}
                        </div>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-filament::button type="button" wire:click="addInstallmentRow" size="xs" color="gray">Add row</x-filament::button>
                        <x-filament::button type="button" wire:click="suggestInstallmentPlan" size="xs" color="gray" outlined>Suggest 50/50 plan</x-filament::button>
                        <x-filament::button type="button" wire:click="fillInstallmentBalance" size="xs" color="gray" outlined>Fill balance on last row</x-filament::button>
                    </div>
                    @foreach ($this->installmentPlan as $index => $row)
                        <div class="mt-3 grid gap-2 lg:grid-cols-[1fr_140px_160px_auto]" wire:key="installment-plan-{{ $index }}">
                            <x-filament::input.wrapper>
                                <x-filament::input type="text" wire:model="installmentPlan.{{ $index }}.label" placeholder="Label" />
                            </x-filament::input.wrapper>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" wire:model.live="installmentPlan.{{ $index }}.amount" step="0.01" min="0" placeholder="Amount" />
                        </x-filament::input.wrapper>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    type="date"
                                    wire:model.live.debounce.300ms="installmentPlan.{{ $index }}.due_date"
                                />
                            </x-filament::input.wrapper>
                            <x-filament::button type="button" wire:click="removeInstallmentRow({{ $index }})" size="sm" color="danger" outlined>Remove</x-filament::button>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <x-filament::button
                type="button"
                wire:click="saveAdmissionFeePlan"
                color="primary"
                size="sm"
                class="w-full sm:w-auto"
                :disabled="! $this->canSaveAdmissionFeePlan"
            >
                Save fee plan
            </x-filament::button>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Set discount, misc charges, and installments here or at convert. After enrollment, fee changes move to the Fees tab.
            </p>
        </div>
    @elseif ($activeAdmission->miscFees->isNotEmpty() || $activeAdmission->installmentPlans->isNotEmpty())
        <div class="mt-4 space-y-3 text-sm">
            @if ($activeAdmission->miscFees->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Miscellaneous charges</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($activeAdmission->miscFees as $miscFee)
                            <li class="flex justify-between gap-4">
                                <span>{{ $miscFee->label }}</span>
                                <span class="font-medium">₹{{ number_format((float) $miscFee->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($activeAdmission->use_installment_plan && $activeAdmission->installmentPlans->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Installment plan</p>
                    <ul class="mt-1 space-y-1">
                        @foreach ($activeAdmission->installmentPlans as $plan)
                            <li class="flex justify-between gap-4">
                                <span>{{ $plan->label }} · Due {{ $plan->due_date?->format('d M Y') ?? 'TBD' }}</span>
                                <span class="font-medium">₹{{ number_format((float) $plan->amount, 2) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
@endif
