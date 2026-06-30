@if ($activeAdmission->course_fee !== null)
    @php
        $activeAdmission->loadMissing(['miscFees', 'installmentPlans', 'discountSetBy']);
    @endphp

    <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Course fee</dt>
            <dd class="font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) $activeAdmission->course_fee, 2) }}</dd>
        </div>
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
    </dl>

    @if ($activeAdmission->miscFees->isNotEmpty() || ($activeAdmission->use_installment_plan && $activeAdmission->installmentPlans->isNotEmpty()))
        <div class="mt-4 space-y-3 text-sm">
            @if ($activeAdmission->miscFees->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Miscellaneous charges</p>
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
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Installment plan</p>
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
