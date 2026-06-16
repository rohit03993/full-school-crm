<?php

namespace App\Filament\Forms;

use App\Enums\PaymentMode;
use App\Models\FeeStructure;
use Filament\Forms\Components\DatePicker;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class AddPaymentFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(FeeStructure $feeStructure): array
    {
        $pending = (float) $feeStructure->pending_amount;

        $collector = Auth::user()?->loadMissing('staffProfile');

        return [
            Placeholder::make('collected_by_display')
                ->label('Collected by')
                ->content($collector?->staffCollectorLabel() ?? 'Logged-in staff')
                ->helperText('Saved automatically — no need to select staff.'),
            Placeholder::make('pending_amount_display')
                ->label('Pending fee')
                ->content('₹'.number_format($pending, 2)),
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
                ->minValue(0.01)
                ->maxValue($pending)
                ->step(0.01)
                ->helperText('Cannot exceed pending amount.'),
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
            FileUpload::make('proof_image')
                ->label('Payment proof')
                ->helperText('Voucher image, screenshot or UPI proof · JPG, PNG or PDF · max 5 MB')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(5120)
                ->required()
                ->disk('local')
                ->directory('temp-payment-proofs')
                ->visibility('private'),
        ];
    }
}
