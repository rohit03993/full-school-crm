<?php

namespace App\Filament\Forms;

use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Support\FeePaymentPolicy;
use App\Support\FeePlanCalculator;
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
    public static function fields(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing('installments');
        $pending = round((float) $feeStructure->pending_amount, 2);
        $payableInstallments = $feeStructure->installments
            ->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0)
            ->values();

        $defaultInstallment = $payableInstallments->first();
        $flexible = FeePaymentPolicy::usesFlexibleAllocation();
        $collector = Auth::user()?->loadMissing('staffProfile');
        $format = fn (float $amount): string => FeePlanCalculator::formatRupeeAmount($amount);

        $installmentLine = $defaultInstallment
            ? $defaultInstallment->label.' · ₹'.$format((float) $defaultInstallment->pending_amount).' due'
            .($defaultInstallment->due_date ? ' · '.$defaultInstallment->due_date->format('d M Y') : '')
            : 'No installment schedule';

        return [
            Placeholder::make('payment_snapshot')
                ->label('')
                ->content(new HtmlString(
                    '<div class="grid gap-2 text-sm sm:grid-cols-3">'
                    .'<div class="rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">Balance due</p>'
                    .'<p class="mt-0.5 text-base font-bold text-amber-900 dark:text-amber-100">₹'.$format($pending).'</p></div>'
                    .'<div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5 sm:col-span-2">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Against</p>'
                    .'<p class="mt-0.5 font-semibold text-gray-950 dark:text-white">'.e($installmentLine).'</p>'
                    .'<p class="mt-0.5 text-xs text-gray-500">Collected by '.e($collector?->staffCollectorLabel() ?? 'logged-in staff').'</p>'
                    .'</div></div>'
                ))
                ->columnSpanFull(),
            Section::make('Payment details')
                ->compact()
                ->columns(2)
                ->schema(self::paymentDetailFields(
                    $feeStructure,
                    $payableInstallments,
                    $defaultInstallment,
                    $pending,
                    $flexible,
                )),
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
    protected static function paymentDetailFields(
        FeeStructure $feeStructure,
        $payableInstallments,
        ?FeeInstallment $defaultInstallment,
        float $pending,
        bool $flexible,
    ): array {
        $fields = [];

        if ($payableInstallments->count() > 1) {
            $fields[] = Select::make('fee_installment_id')
                ->label('Installment')
                ->options($payableInstallments->mapWithKeys(function (FeeInstallment $row): array {
                    $due = $row->due_date?->format('d M Y') ?? 'No due date';

                    return [$row->id => "{$row->label} · ₹".FeePlanCalculator::formatRupeeAmount((float) $row->pending_amount)." · {$due}"];
                }))
                ->default($defaultInstallment?->id)
                ->required()
                ->native(false)
                ->live()
                ->columnSpanFull()
                ->afterStateUpdated(function (mixed $state, callable $set) use ($payableInstallments): void {
                    $installment = $payableInstallments->firstWhere('id', (int) $state);

                    if ($installment) {
                        $set('amount', (string) FeePlanCalculator::toWholeRupeeAmount((float) $installment->pending_amount));
                    }

                    $set('shortfall_action', null);
                    $set('shortfall_due_date', null);
                });
        } elseif ($defaultInstallment) {
            $fields[] = Hidden::make('fee_installment_id')
                ->default($defaultInstallment->id);
        }

        $fields[] = DatePicker::make('payment_date')
            ->label('Payment date')
            ->default(now())
            ->required()
            ->native(false)
            ->maxDate(now());

        $fields[] = TextInput::make('amount')
            ->label('Amount (₹)')
            ->numeric()
            ->required()
            ->minValue(1)
            ->live(debounce: 300)
            ->default(fn (): ?string => $defaultInstallment
                ? (string) FeePlanCalculator::toWholeRupeeAmount((float) $defaultInstallment->pending_amount)
                : ($pending > 0 ? (string) FeePlanCalculator::toWholeRupeeAmount($pending) : null))
            ->maxValue(FeePlanCalculator::toWholeRupeeAmount($pending))
            ->step(1)
            ->helperText('You may pay more than one installment; extra reduces future dues automatically.');

        $fields[] = Placeholder::make('surplus_notice')
            ->label('Extra amount')
            ->content(function (Get $get) use ($payableInstallments, $defaultInstallment): string {
                $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                return PaymentShortfallHelper::surplusForwardPreview(
                    (float) ($get('amount') ?? 0),
                    $installment,
                    $payableInstallments,
                ) ?? '';
            })
            ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                return PaymentShortfallHelper::surplusAmount((float) ($get('amount') ?? 0), $installment) > 0;
            })
            ->extraAttributes(['class' => 'text-sm font-medium text-primary-600 dark:text-primary-400'])
            ->columnSpanFull();

        if ($flexible && $defaultInstallment) {
            $fields[] = Placeholder::make('shortfall_notice')
                ->label('Remaining on this installment')
                ->content(function (Get $get) use ($payableInstallments, $defaultInstallment): string {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);
                    $shortfall = PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment);

                    if ($shortfall <= 0 || ! $installment) {
                        return '';
                    }

                    return '₹'.FeePlanCalculator::formatRupeeAmount($shortfall).' from '.$installment->label.' stays unpaid. Choose how to schedule it below.';
                })
                ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->columnSpanFull();

            $fields[] = Select::make('shortfall_action')
                ->label('Handle remaining balance')
                ->options(function (Get $get) use ($payableInstallments, $defaultInstallment, $feeStructure): array {
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
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->native(false)
                ->live()
                ->visible(function (Get $get) use ($payableInstallments, $defaultInstallment): bool {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment) > 0;
                })
                ->columnSpanFull();

            $fields[] = TextInput::make('shortfall_label')
                ->label('New installment label')
                ->default(fn (): string => PaymentShortfallHelper::suggestNewInstallmentLabel($feeStructure->id))
                ->maxLength(100)
                ->visible(fn (Get $get): bool => $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value)
                ->required(fn (Get $get): bool => $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value);

            $fields[] = DatePicker::make('shortfall_due_date')
                ->label('New installment due date')
                ->native(false)
                ->default(now()->addMonth())
                ->minDate(function (Get $get) use ($payableInstallments, $defaultInstallment): ?\Illuminate\Support\Carbon {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                    return $installment?->due_date;
                })
                ->visible(fn (Get $get): bool => $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value)
                ->required(fn (Get $get): bool => $get('shortfall_action') === PaymentShortfallAction::NewInstallment->value);
        }

        $fields[] = Select::make('payment_mode')
            ->label('Payment mode')
            ->options(collect(PaymentMode::cases())->mapWithKeys(
                fn (PaymentMode $mode) => [$mode->value => $mode->label()],
            ))
            ->required()
            ->native(false)
            ->live();

        $fields[] = TextInput::make('voucher_number')
            ->label('Voucher number')
            ->maxLength(100)
            ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Cash->value)
            ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Cash->value);

        $fields[] = TextInput::make('transaction_id')
            ->label('Transaction ID')
            ->maxLength(100)
            ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Online->value)
            ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Online->value);

        $fields[] = TextInput::make('utr_number')
            ->label('UTR number')
            ->maxLength(100)
            ->visible(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Upi->value)
            ->required(fn (Get $get): bool => $get('payment_mode') === PaymentMode::Upi->value);

        return $fields;
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
}
