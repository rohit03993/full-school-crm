<?php

namespace App\Filament\Forms;

use App\Models\FeeStructure;
use App\Support\FeePlanCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

class AdjustFeeStructureFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing(['miscCharges', 'installments']);

        $paid = round((float) $feeStructure->paid_amount, 2);
        $pending = round((float) $feeStructure->pending_amount, 2);
        $miscTotal = $feeStructure->miscChargesTotal();

        return [
            Placeholder::make('fee_snapshot')
                ->label('Current position')
                ->content(
                    'Paid: ₹'.number_format($paid, 2)
                    .' · Pending: ₹'.number_format($pending, 2)
                    .($miscTotal > 0 ? ' · Misc charges: ₹'.number_format($miscTotal, 2) : '')
                )
                ->columnSpanFull(),
            TextInput::make('course_fee')
                ->label('Course fee')
                ->numeric()
                ->prefix('₹')
                ->required()
                ->minValue(0)
                ->step(0.01)
                ->live(debounce: 300),
            TextInput::make('discount_amount')
                ->label('Discount')
                ->numeric()
                ->prefix('₹')
                ->required()
                ->minValue(0)
                ->step(0.01)
                ->live(debounce: 300),
            Placeholder::make('new_net_fee_preview')
                ->label('New net fee (preview)')
                ->content(function (Get $get) use ($feeStructure, $miscTotal): string {
                    $courseFee = max(0, (float) ($get('course_fee') ?? $feeStructure->course_fee));
                    $discount = max(0, (float) ($get('discount_amount') ?? $feeStructure->discount_amount));
                    $net = round($courseFee - $discount + $miscTotal, 2);
                    $paid = round((float) $feeStructure->paid_amount, 2);
                    $newPending = round(max(0, $net - $paid), 2);

                    return 'Net ₹'.number_format($net, 2).' · Remaining to schedule: ₹'.number_format($newPending, 2);
                })
                ->columnSpanFull(),
            Toggle::make('reschedule_installments')
                ->label('Reschedule remaining installments')
                ->helperText('Rebuild the payment schedule for the pending balance. Paid installments are kept on record.')
                ->default($pending > 0)
                ->live()
                ->columnSpanFull(),
            Placeholder::make('paid_installments_snapshot')
                ->label('Paid installments (locked)')
                ->content(fn (): HtmlString => new HtmlString(
                    nl2br(e(self::paidInstallmentsSummary($feeStructure))),
                ))
                ->visible(fn (): bool => self::paidInstallmentsSummary($feeStructure) !== '')
                ->helperText('These installments are already settled and cannot be changed here.')
                ->columnSpanFull(),
            Placeholder::make('installment_allocation_summary')
                ->label('Installment allocation')
                ->content(function (Get $get) use ($feeStructure, $miscTotal): string {
                    $courseFee = max(0, (float) ($get('course_fee') ?? $feeStructure->course_fee));
                    $discount = max(0, (float) ($get('discount_amount') ?? $feeStructure->discount_amount));
                    $net = round($courseFee - $discount + $miscTotal, 2);
                    $target = round(max(0, $net - (float) $feeStructure->paid_amount), 2);

                    return FeePlanCalculator::formatSummary($target, $get('installment_plan') ?? []);
                })
                ->visible(fn (Get $get): bool => (bool) $get('reschedule_installments'))
                ->columnSpanFull(),
            Placeholder::make('installment_unallocated_warning')
                ->label('')
                ->content(function (Get $get) use ($feeStructure, $miscTotal): string {
                    $courseFee = max(0, (float) ($get('course_fee') ?? $feeStructure->course_fee));
                    $discount = max(0, (float) ($get('discount_amount') ?? $feeStructure->discount_amount));
                    $net = round($courseFee - $discount + $miscTotal, 2);
                    $target = round(max(0, $net - (float) $feeStructure->paid_amount), 2);

                    return FeePlanCalculator::unallocatedWarningMessage($target, $get('installment_plan') ?? []) ?? '';
                })
                ->visible(function (Get $get) use ($feeStructure, $miscTotal): bool {
                    if (! $get('reschedule_installments')) {
                        return false;
                    }

                    $courseFee = max(0, (float) ($get('course_fee') ?? $feeStructure->course_fee));
                    $discount = max(0, (float) ($get('discount_amount') ?? $feeStructure->discount_amount));
                    $net = round($courseFee - $discount + $miscTotal, 2);
                    $target = round(max(0, $net - (float) $feeStructure->paid_amount), 2);

                    return FeePlanCalculator::unallocatedWarningMessage($target, $get('installment_plan') ?? []) !== null;
                })
                ->extraAttributes(['class' => 'text-sm font-medium text-danger-600 dark:text-danger-400'])
                ->columnSpanFull(),
            AdmissionFeePlanFormSchema::configureInstallmentRepeater(
                Repeater::make('installment_plan')
                    ->label('Pending installment schedule')
                    ->columns(3)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->visible(fn (Get $get): bool => (bool) $get('reschedule_installments'))
                    ->live(),
                function (Repeater $component) use ($feeStructure, $miscTotal): float {
                    $livewire = $component->getLivewire();
                    $mounted = $livewire->mountedActionsData[0] ?? [];
                    $courseFee = max(0, (float) (($mounted['course_fee'] ?? $feeStructure->course_fee)));
                    $discount = max(0, (float) (($mounted['discount_amount'] ?? $feeStructure->discount_amount)));
                    $net = round($courseFee - $discount + $miscTotal, 2);

                    return round(max(0, $net - (float) $feeStructure->paid_amount), 2);
                },
            ),
            Textarea::make('reason')
                ->label('Reason for change')
                ->required()
                ->rows(3)
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing('installments');

        $unpaidPlan = $feeStructure->installments
            ->filter(fn ($row): bool => (float) $row->pending_amount > 0)
            ->sortBy(fn ($row): array => [
                $row->due_date?->toDateString() ?? '9999-12-31',
                $row->sort_order,
                $row->id,
            ])
            ->values()
            ->map(fn ($row): array => [
                'label' => $row->label,
                'amount' => (string) $row->pending_amount,
                'due_date' => $row->due_date?->toDateString(),
            ])
            ->all();

        return [
            'course_fee' => $feeStructure->course_fee,
            'discount_amount' => $feeStructure->discount_amount,
            'reschedule_installments' => (float) $feeStructure->pending_amount > 0,
            'installment_plan' => $unpaidPlan,
            'reason' => '',
        ];
    }

    public static function paidInstallmentsSummary(FeeStructure $feeStructure): string
    {
        $feeStructure->loadMissing('installments');

        $lines = $feeStructure->installments
            ->filter(fn ($row): bool => (float) $row->paid_amount > 0 && (float) $row->pending_amount <= 0.01)
            ->sortBy(fn ($row): array => [
                $row->due_date?->toDateString() ?? '0000-01-01',
                $row->sort_order,
                $row->id,
            ])
            ->map(function ($row): string {
                $due = $row->due_date?->format('d M Y') ?? 'TBD';

                return sprintf(
                    '• %s — due %s — paid ₹%s',
                    $row->label,
                    $due,
                    number_format((float) $row->paid_amount, 2),
                );
            })
            ->values()
            ->all();

        return implode("\n", $lines);
    }
}
