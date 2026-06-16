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
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="number"
                            wire:model.live="discountAmount"
                            step="0.01"
                            min="0"
                        />
                    </x-filament::input.wrapper>
                    @error('discountAmount')<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
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
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Net fee</dt>
                <dd class="font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) $activeAdmission->net_fee, 2) }}</dd>
            </div>
        @endif
    </dl>

    @if ($activeAdmission->canAdjustFees())
        <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
            <x-filament::button
                type="button"
                wire:click="saveAdmissionDiscount"
                color="gray"
                size="sm"
                class="w-full sm:w-auto"
            >
                Save discount
            </x-filament::button>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Set discount here or at convert. After enrollment, fee changes move to the Fees tab.
            </p>
        </div>
    @endif
@endif
