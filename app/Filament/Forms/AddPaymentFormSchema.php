<?php

namespace App\Filament\Forms;

use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Support\FeePaymentPolicy;
use App\Support\PaymentShortfallHelper;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;

class AddPaymentFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing('installments');
        $pending = (float) $feeStructure->pending_amount;
        $payableInstallments = $feeStructure->installments
            ->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0)
            ->values();

        $defaultInstallment = $payableInstallments->first();
        $flexible = FeePaymentPolicy::usesFlexibleAllocation();
        $collector = Auth::user()?->loadMissing('staffProfile');

        $fields = [
            Placeholder::make('collected_by_display')
                ->label('Collected by')
                ->content($collector?->staffCollectorLabel() ?? 'Logged-in staff')
                ->helperText('Saved automatically — no need to select staff.'),
            Placeholder::make('pending_amount_display')
                ->label('Pending fee')
                ->content('₹'.number_format($pending, 2)),
        ];

        if ($payableInstallments->count() > 1) {
            $fields[] = Select::make('fee_installment_id')
                ->label('Installment')
                ->options($payableInstallments->mapWithKeys(function (FeeInstallment $row): array {
                    $due = $row->due_date?->format('d M Y') ?? 'No due date';
                    $label = "{$row->label} · ₹".number_format((float) $row->pending_amount, 2)." pending · Due {$due}";

                    return [$row->id => $label];
                }))
                ->default($defaultInstallment?->id)
                ->required()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (mixed $state, callable $set) use ($payableInstallments): void {
                    $installment = $payableInstallments->firstWhere('id', (int) $state);

                    if ($installment) {
                        $set('amount', (string) round((float) $installment->pending_amount, 2));
                    }

                    $set('shortfall_action', null);
                    $set('shortfall_due_date', null);
                });
        } elseif ($defaultInstallment) {
            $fields[] = Hidden::make('fee_installment_id')
                ->default($defaultInstallment->id);
            $fields[] = Placeholder::make('installment_display')
                ->label('Installment')
                ->content($defaultInstallment->label.' · ₹'.number_format((float) $defaultInstallment->pending_amount, 2).' pending');
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
            ->minValue(0.01)
            ->live(debounce: 300)
            ->default(fn (): ?string => $defaultInstallment
                ? (string) round((float) $defaultInstallment->pending_amount, 2)
                : ($pending > 0 ? (string) round($pending, 2) : null))
            ->maxValue(function (Get $get) use ($feeStructure, $payableInstallments, $pending, $flexible): float {
                if ($flexible || $payableInstallments->count() <= 1) {
                    return $pending;
                }

                $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);

                return min($pending, (float) ($installment?->pending_amount ?? $pending));
            })
            ->step(0.01)
            ->helperText(fn (): string => $flexible
                ? 'Enter less than the installment due to see balance options below.'
                : ($payableInstallments->count() > 1
                    ? 'Cannot exceed the selected installment pending amount.'
                    : 'Cannot exceed pending amount.'));

        if ($flexible && $defaultInstallment) {
            $fields[] = Placeholder::make('shortfall_notice')
                ->label('Installment balance')
                ->content(function (Get $get) use ($payableInstallments, $defaultInstallment): string {
                    $installment = self::selectedInstallment($get, $payableInstallments, $defaultInstallment);
                    $shortfall = PaymentShortfallHelper::shortfallAmount((float) ($get('amount') ?? 0), $installment);

                    if ($shortfall <= 0 || ! $installment) {
                        return '';
                    }

                    return 'Remaining ₹'.number_format($shortfall, 2).' from '.$installment->label.' is unpaid. Choose how to schedule it below.';
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

        $fields[] = FileUpload::make('proof_image')
            ->label('Payment proof')
            ->helperText('Voucher image, screenshot or UPI proof · JPG, PNG or PDF · max 5 MB')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
            ->maxSize(5120)
            ->required()
            ->disk('local')
            ->directory('temp-payment-proofs')
            ->visibility('private');

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
