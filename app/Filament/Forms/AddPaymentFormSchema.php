<?php

namespace App\Filament\Forms;

use App\Enums\FeeMiscChargeStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Models\FeeInstallment;
use App\Models\FeeMiscCharge;
use App\Models\FeeStructure;
use App\Support\FeePaymentPolicy;
use App\Support\FeePlanCalculator;
use App\Support\FeeSettings;
use App\Support\PaymentShortfallHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class AddPaymentFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function fields(FeeStructure $feeStructure, ?int $prefillMiscChargeId = null): array
    {
        $feeStructure->loadMissing(['installments', 'miscCharges']);
        $tuitionPending = round((float) $feeStructure->pending_amount, 2);
        $miscPending = round((float) $feeStructure->separateMiscChargesPendingTotal(), 2);
        $collectible = round((float) $feeStructure->totalCollectiblePending(), 2);
        $payableMisc = $feeStructure->separateMiscCharges()
            ->filter(fn (FeeMiscCharge $charge): bool => $charge->isPayableSeparately())
            ->values();
        $payableInstallments = $feeStructure->installments
            ->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0)
            ->values();
        $defaultInstallment = $payableInstallments->first();
        $defaultMisc = $prefillMiscChargeId
            ? $payableMisc->firstWhere('id', $prefillMiscChargeId)
            : $payableMisc->first();
        $flexible = FeePaymentPolicy::usesFlexibleAllocation();
        $collector = Auth::user()?->loadMissing('staffProfile');
        $format = fn (float $amount): string => FeePlanCalculator::formatRupeeAmount($amount);

        $defaultTarget = match (true) {
            $prefillMiscChargeId && $defaultMisc => 'misc',
            $tuitionPending > 0 => 'tuition',
            $miscPending > 0 => 'misc',
            default => 'tuition',
        };
        $needsCashOnlineSplit = FeeSettings::onlineAllowanceGstEnabled() && ! $feeStructure->hasOnlineAllowancePlan();
        $initialAmount = self::suggestedAmount(
            $defaultTarget,
            $defaultInstallment,
            $defaultMisc,
            $tuitionPending,
        );

        return [
            Placeholder::make('gst_split_warning')
                ->label('')
                ->content(new HtmlString(
                    '<div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">'
                    .'<p class="font-semibold">Cash / online split not set</p>'
                    .'<p class="mt-1 text-xs leading-relaxed text-amber-800 dark:text-amber-200">'
                    .'GST tracking is enabled but this student has no agreed cash vs online split. '
                    .'Open <strong>Adjust Fees → Cash / online</strong> to set it before recording online or UPI tuition payments.'
                    .'</p></div>'
                ))
                ->visible($needsCashOnlineSplit)
                ->columnSpanFull(),
            Placeholder::make('payment_snapshot')
                ->label('')
                ->content(new HtmlString(
                    '<div class="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-4">'
                    .'<div class="rounded-lg bg-primary-50 px-3 py-2 dark:bg-primary-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-primary-800 dark:text-primary-300">Net tuition fee</p>'
                    .'<p class="mt-0.5 text-base font-bold text-primary-950 dark:text-primary-100">₹'.$format((float) $feeStructure->net_fee).'</p>'
                    .'<p class="mt-0.5 text-xs text-primary-800/80 dark:text-primary-200">Paid ₹'.$format((float) $feeStructure->paid_amount).' · Pending ₹'.$format($tuitionPending).'</p></div>'
                    .'<div class="rounded-lg bg-violet-50 px-3 py-2 dark:bg-violet-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">Misc charges</p>'
                    .'<p class="mt-0.5 text-base font-bold text-violet-950 dark:text-violet-100">₹'.$format($feeStructure->separateMiscChargesTotal()).'</p>'
                    .'<p class="mt-0.5 text-xs text-violet-800/80 dark:text-violet-200">Paid ₹'.$format($feeStructure->separateMiscChargesPaidTotal()).' · Pending ₹'.$format($miscPending).' · includes late fees & GST</p></div>'
                    .'<div class="rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">Total due now</p>'
                    .'<p class="mt-0.5 text-base font-bold text-amber-950 dark:text-amber-100">₹'.$format($collectible).'</p></div>'
                    .'<div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Collector</p>'
                    .'<p class="mt-0.5 font-semibold text-gray-950 dark:text-white">'.e($collector?->staffCollectorLabel() ?? 'Staff').'</p></div>'
                    .'</div>'
                ))
                ->columnSpanFull(),
            Section::make('Payment details')
                ->compact()
                ->columns(2)
                ->schema([
                    Select::make('payment_target')
                        ->label('Pay against')
                        ->options(array_filter([
                            'tuition' => $tuitionPending > 0 ? 'Tuition / installments' : null,
                            'misc' => $payableMisc->isNotEmpty() ? 'Miscellaneous charge' : null,
                        ]))
                        ->default($defaultTarget)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (mixed $state, callable $set, Get $get) use (
                            $payableInstallments,
                            $defaultInstallment,
                            $payableMisc,
                            $defaultMisc,
                            $tuitionPending,
                        ): void {
                            if ($state === 'misc') {
                                if (! filled($get('fee_misc_charge_id')) && $defaultMisc) {
                                    $set('fee_misc_charge_id', $defaultMisc->id);
                                }

                                $charge = self::selectedMiscCharge($get, $defaultMisc, $payableMisc);
                                $set('amount', (string) self::suggestedAmount('misc', null, $charge, 0));

                                return;
                            }

                            $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);
                            $set('amount', (string) self::suggestedAmount('tuition', $installment, null, $tuitionPending));
                            $set('shortfall_action', null);
                            $set('shortfall_due_date', null);
                        })
                        ->columnSpanFull(),
                    ...self::tuitionFields(
                        $feeStructure,
                        $payableInstallments,
                        $defaultInstallment,
                        $tuitionPending,
                        $flexible,
                    ),
                    ...self::miscFields($payableMisc, $defaultMisc),
                    DatePicker::make('payment_date')
                        ->label('Payment date')
                        ->default(now())
                        ->required()
                        ->native(false)
                        ->maxDate(now()),
                    TextInput::make('amount')
                        ->label('Amount (₹)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->live(debounce: 300)
                        ->default((string) $initialAmount)
                        ->maxValue(function (Get $get) use ($payableInstallments, $defaultInstallment, $defaultMisc, $payableMisc, $tuitionPending): int {
                            if ($get('payment_target') === 'misc') {
                                $charge = self::selectedMiscCharge($get, $defaultMisc, $payableMisc);

                                return $charge
                                    ? FeePlanCalculator::toWholeRupeeAmount($charge->pendingAmount())
                                    : 1;
                            }

                            return FeePlanCalculator::toWholeRupeeAmount($tuitionPending);
                        })
                        ->step(1)
                        ->helperText(fn (Get $get): string => $get('payment_target') === 'misc'
                            ? 'Partial payments are allowed on misc charges.'
                            : 'You may pay more than one installment; extra reduces future dues automatically.'),
                    ...self::tuitionShortfallFields($feeStructure, $payableInstallments, $defaultInstallment, $flexible),
                    Select::make('payment_mode')
                        ->label('Payment mode')
                        ->options(collect(PaymentMode::cases())->mapWithKeys(
                            fn (PaymentMode $mode) => [$mode->value => $mode->label()],
                        ))
                        ->required()
                        ->native(false)
                        ->live(),
                    TextInput::make('voucher_number')
                        ->label('Voucher number')
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Cash->value)
                        ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Cash->value),
                    TextInput::make('transaction_id')
                        ->label('Transaction ID')
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Online->value)
                        ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Online->value),
                    TextInput::make('utr_number')
                        ->label('UTR number')
                        ->maxLength(100)
                        ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Upi->value)
                        ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Upi->value),
                ]),
            Section::make('Proof')
                ->compact()
                ->schema([
                    FileUpload::make('proof_image')
                        ->label('Payment proof')
                        ->helperText('JPG, PNG or PDF · max 5 MB')
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5120)
                        ->required()
                        ->disk('local')
                        ->directory('temp-payment-proofs')
                        ->visibility('private')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FeeInstallment>  $payableInstallments
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function tuitionFields(
        FeeStructure $feeStructure,
        $payableInstallments,
        ?FeeInstallment $defaultInstallment,
        float $tuitionPending,
        bool $flexible,
    ): array {
        return [
            Select::make('fee_installment_id')
                ->label('Installment')
                ->options($payableInstallments->mapWithKeys(function (FeeInstallment $row): array {
                    $due = $row->due_date?->format('d M Y') ?? 'No due date';

                    return [$row->id => "{$row->label} · ₹".FeePlanCalculator::formatRupeeAmount((float) $row->pending_amount)." · {$due}"];
                }))
                ->default($defaultInstallment?->id)
                ->required(fn (Get $get): bool => $get('payment_target') === 'tuition' && $payableInstallments->count() > 1)
                ->native(false)
                ->live()
                ->visible(fn (Get $get): bool => $get('payment_target') === 'tuition' && $payableInstallments->count() > 1)
                ->columnSpanFull()
                ->afterStateUpdated(function (mixed $state, callable $set) use ($payableInstallments): void {
                    $installment = $payableInstallments->firstWhere('id', (int) $state);

                    if ($installment) {
                        $set('amount', (string) FeePlanCalculator::toWholeRupeeAmount((float) $installment->pending_amount));
                    }

                    $set('shortfall_action', null);
                    $set('shortfall_due_date', null);
                }),
            Hidden::make('fee_installment_id')
                ->default($defaultInstallment?->id)
                ->visible(fn (Get $get): bool => $get('payment_target') === 'tuition' && $payableInstallments->count() === 1),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FeeMiscCharge>  $payableMisc
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function miscFields($payableMisc, ?FeeMiscCharge $defaultMisc): array
    {
        return [
            Select::make('fee_misc_charge_id')
                ->label('Misc charge')
                ->options($payableMisc->mapWithKeys(function (FeeMiscCharge $charge): array {
                    $pending = FeePlanCalculator::formatRupeeAmount($charge->pendingAmount());
                    $total = FeePlanCalculator::formatRupeeAmount((float) $charge->amount);

                    return [$charge->id => "{$charge->label} · ₹{$pending} pending of ₹{$total}"];
                }))
                ->default($defaultMisc?->id)
                ->required(fn (Get $get): bool => $get('payment_target') === 'misc')
                ->native(false)
                ->live()
                ->visible(fn (Get $get): bool => $get('payment_target') === 'misc')
                ->columnSpanFull()
                ->afterStateUpdated(function (mixed $state, callable $set) use ($payableMisc): void {
                    $charge = $payableMisc->firstWhere('id', (int) $state);

                    if ($charge) {
                        $set('amount', (string) self::suggestedAmount('misc', null, $charge, 0));
                    }
                }),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FeeInstallment>  $payableInstallments
     * @return array<int, \Filament\Forms\Components\Component>
     */
    protected static function tuitionShortfallFields(
        FeeStructure $feeStructure,
        $payableInstallments,
        ?FeeInstallment $defaultInstallment,
        bool $flexible,
    ): array {
        if (! $flexible || ! $defaultInstallment) {
            return [];
        }

        return [
            Placeholder::make('surplus_notice')
                ->label('Extra amount')
                ->content(function (Get $get) use ($payableInstallments, $defaultInstallment): string {
                    if ($get('payment_target') !== 'tuition') {
                        return '';
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::surplusForwardPreview(
                        (float) ($get('amount') ?? 0),
                        $installment,
                        $payableInstallments,
                    ) ?? '';
                })
                ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    if ($get('payment_target') !== 'tuition') {
                        return false;
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::surplusAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->extraAttributes(['class' => 'text-sm font-medium text-primary-600 dark:text-primary-400'])
                ->columnSpanFull(),
            Placeholder::make('shortfall_notice')
                ->label('Remaining on this installment')
                ->content(function (Get $get) use ($payableInstallments, $defaultInstallment): string {
                    if ($get('payment_target') !== 'tuition') {
                        return '';
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);
                    $shortfall = PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment);

                    if ($shortfall <= 0 || ! $installment) {
                        return '';
                    }

                    return '₹'.FeePlanCalculator::formatRupeeAmount($shortfall).' from '.$installment->label.' stays unpaid. Choose how to schedule it below.';
                })
                ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    if ($get('payment_target') !== 'tuition') {
                        return false;
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->columnSpanFull(),
            Select::make('shortfall_action')
                ->label('Handle remaining balance')
                ->options(function (Get $get) use ($payableInstallments, $defaultInstallment): array {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);
                    $options = [];

                    if ($installment && PaymentShortfallHelper::hasNextPayableInstallment($installment)) {
                        $options[PaymentShortfallAction::CarryForward->value] = PaymentShortfallAction::CarryForward->label();
                    }

                    $options[PaymentShortfallAction::NewInstallment->value] = PaymentShortfallAction::NewInstallment->label();

                    return $options;
                })
                ->default(function (Get $get) use ($payableInstallments, $defaultInstallment): ?string {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    if ($installment && PaymentShortfallHelper::hasNextPayableInstallment($installment)) {
                        return PaymentShortfallAction::CarryForward->value;
                    }

                    return PaymentShortfallAction::NewInstallment->value;
                })
                ->required(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    if ($get('payment_target') !== 'tuition') {
                        return false;
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->native(false)
                ->live()
                ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    if ($get('payment_target') !== 'tuition') {
                        return false;
                    }

                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->columnSpanFull(),
            TextInput::make('shortfall_label')
                ->label('New installment label')
                ->default(fn (): string => PaymentShortfallHelper::suggestNewInstallmentLabel($feeStructure->id))
                ->maxLength(100)
                ->visible(fn (Get $get): bool => $get('payment_target') === 'tuition' && $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value)
                ->required(fn (Get $get): bool => $get('payment_target') === 'tuition' && $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value),
            DatePicker::make('shortfall_due_date')
                ->label('New installment due date')
                ->native(false)
                ->default(now()->addMonth())
                ->minDate(function (Get $get) use ($payableInstallments, $defaultInstallment): ?\Illuminate\Support\Carbon {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return $installment?->due_date;
                })
                ->visible(fn (Get $get): bool => $get('payment_target') === 'tuition' && $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value)
                ->required(fn (Get $get): bool => $get('payment_target') === 'tuition' && $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FeeInstallment>  $payableInstallments
     */
    protected static function selectedInstallment(
        Get $get,
        $payableInstallments,
        ?FeeInstallment $defaultInstallment,
    ): ?FeeInstallment {
        return PaymentShortfallHelper::resolveInstallment(
            $get('fee_installment_id'),
            $payableInstallments,
            $defaultInstallment,
        );
    }

    protected static function selectedMiscCharge(
        Get $get,
        ?FeeMiscCharge $defaultMisc,
        $payableMisc = null,
    ): ?FeeMiscCharge {
        if (filled($get('fee_misc_charge_id'))) {
            $chargeId = (int) $get('fee_misc_charge_id');

            if ($payableMisc) {
                return $payableMisc->firstWhere('id', $chargeId)
                    ?? FeeMiscCharge::query()->find($chargeId)
                    ?? $defaultMisc;
            }

            return FeeMiscCharge::query()->find($chargeId) ?? $defaultMisc;
        }

        return $defaultMisc;
    }

    protected static function suggestedAmount(
        string $target,
        ?FeeInstallment $installment,
        ?FeeMiscCharge $miscCharge,
        float $tuitionPending,
    ): int {
        if ($target === 'misc' && $miscCharge) {
            return FeePlanCalculator::toWholeRupeeAmount($miscCharge->pendingAmount());
        }

        if ($installment) {
            return FeePlanCalculator::toWholeRupeeAmount((float) $installment->pending_amount);
        }

        return $tuitionPending > 0
            ? FeePlanCalculator::toWholeRupeeAmount($tuitionPending)
            : 1;
    }
}
