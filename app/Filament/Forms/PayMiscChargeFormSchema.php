<?php

namespace App\Filament\Forms;

use App\Enums\PaymentMode;
use App\Models\FeeMiscCharge;
use App\Support\FeePlanCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PayMiscChargeFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function fields(FeeMiscCharge $charge): array
    {
        $total = round((float) $charge->amount, 2);
        $paid = round((float) $charge->paid_amount, 2);
        $pending = $charge->pendingAmount();
        $collector = Auth::user()?->loadMissing('staffProfile');
        $format = fn (float $amount): string => FeePlanCalculator::formatRupeeAmount($amount);

        return [
            Hidden::make('fee_misc_charge_id')
                ->default($charge->id),
            Placeholder::make('misc_snapshot')
                ->label('')
                ->content(new HtmlString(
                    '<div class="rounded-lg bg-amber-50 px-3 py-2 text-sm dark:bg-amber-500/10">'
                    .'<p class="font-semibold text-amber-900 dark:text-amber-100">'.e($charge->label).'</p>'
                    .'<p class="mt-1 text-lg font-bold text-amber-950 dark:text-amber-50">₹'.$format($pending).' pending</p>'
                    .'<p class="mt-1 text-xs text-amber-800 dark:text-amber-200">Total ₹'.$format($total)
                    .' · Paid ₹'.$format($paid)
                    .' · collected by '.e($collector?->staffCollectorLabel() ?? 'staff').'</p></div>'
                ))
                ->columnSpanFull(),
            Section::make('Payment details')
                ->compact()
                ->columns(2)
                ->schema([
                    DatePicker::make('payment_date')
                        ->default(now()->toDateString())
                        ->required()
                        ->native(false),
                    TextInput::make('amount')
                        ->label('Amount (₹)')
                        ->numeric()
                        ->required()
                        ->default((string) FeePlanCalculator::toWholeRupeeAmount($pending))
                        ->minValue(1)
                        ->maxValue(FeePlanCalculator::toWholeRupeeAmount($pending))
                        ->step(1)
                        ->helperText('Partial payments are allowed.'),
                    Select::make('payment_mode')
                        ->options(collect(PaymentMode::cases())->mapWithKeys(
                            fn (PaymentMode $mode): array => [$mode->value => $mode->label()],
                        )->all())
                        ->required()
                        ->native(false)
                        ->live(),
                    TextInput::make('voucher_number')
                        ->label('Voucher number')
                        ->visible(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Cash->value)
                        ->required(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Cash->value),
                    TextInput::make('transaction_id')
                        ->label('Transaction ID')
                        ->visible(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Online->value)
                        ->required(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Online->value),
                    TextInput::make('utr_number')
                        ->label('UTR number')
                        ->visible(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Upi->value)
                        ->required(fn (callable $get): bool => $get('payment_mode') === PaymentMode::Upi->value),
                ]),
            FileUpload::make('proof_image')
                ->label('Payment proof')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->required()
                ->disk('local')
                ->directory('temp-payment-proofs')
                ->visibility('private')
                ->columnSpanFull(),
        ];
    }
}
