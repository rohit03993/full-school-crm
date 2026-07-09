@if ($activeAdmission->course_fee !== null)
    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Course fee (from course master)</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) $activeAdmission->course_fee, 2) }}</dd>
        </div>
        @if ($activeAdmission->status->value === 'approved' || ! $activeAdmission->canAdjustFees())
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Recorded net fee</dt>
                <dd class="font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) $activeAdmission->net_fee, 2) }}</dd>
            </div>
        @endif
    </dl>

    <div class="mt-4 rounded-xl border border-primary-500/20 bg-primary-500/5 px-4 py-3 text-sm text-primary-950 dark:text-primary-100">
        @if ($activeAdmission->canAdjustFees())
            <p class="font-semibold">Set the fee plan before approval</p>
            <p class="mt-1 text-primary-900/80 dark:text-primary-100/80">
                Use <strong>Set fee plan</strong> on the profile header to add discount, installments, and misc charges.
                After approval, use <strong>Adjust Fees</strong> on the Fees tab.
            </p>
        @else
            <p class="font-semibold">Fees are managed after enrollment</p>
            <p class="mt-1 text-primary-900/80 dark:text-primary-100/80">
                Discount, installments, misc charges, and cash/online split are set from
                <strong>Adjust Fees</strong> on the student profile once admission is approved.
            </p>
        @endif
    </div>

    @if ($activeAdmission->miscFees->isNotEmpty() || ($activeAdmission->use_installment_plan && $activeAdmission->installmentPlans->isNotEmpty()))
        <div class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
            <p class="text-xs text-gray-500 dark:text-gray-400">Legacy fee plan saved before enrollment (read-only):</p>
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
