<?php

namespace App\Filament\Forms;

use App\Models\FeeStructure;
use App\Support\FeePlanCalculator;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
        $currentNet = round((float) $feeStructure->net_fee, 2);
        $currentDiscount = round((float) $feeStructure->discount_amount, 2);

        $rebalanceInstallments = function (Get $get, Set $set) use ($feeStructure, $miscTotal): void {
            if ((float) ($get('discount_adjustment') ?? $get('additional_discount') ?? 0) != 0.0
                || round((float) ($get('course_fee') ?? $feeStructure->course_fee), 2) !== round((float) $feeStructure->course_fee, 2)) {
                $set('reschedule_installments', true);
            }

            if (! (bool) $get('reschedule_installments')) {
                return;
            }

            $target = self::scheduleTarget($feeStructure, $get, $miscTotal);
            $plan = $get('installment_plan') ?? [];

            if ($plan === [] && $target > 0) {
                $set('installment_plan', [[
                    'label' => 'Balance due',
                    'amount' => (string) $target,
                    'due_date' => null,
                ]]);

                return;
            }

            if ($plan !== []) {
                $set('installment_plan', FeePlanCalculator::fillBalanceOnLastRow($plan, $target));
            }
        };

        return [
            Placeholder::make('current_net_base')
                ->label('Current net fee (your base)')
                ->content('₹'.number_format($currentNet, 2))
                ->helperText(
                    'Listed course fee ₹'.number_format((float) $feeStructure->course_fee, 2)
                    .' · Discount so far ₹'.number_format($currentDiscount, 2)
                    .($miscTotal > 0 ? ' · Misc ₹'.number_format($miscTotal, 2) : '')
                )
                ->columnSpanFull(),
            Placeholder::make('paid_snapshot')
                ->label('Already paid (fixed)')
                ->content('₹'.number_format($paid, 2))
                ->helperText('Payments already collected stay as-is. New balance = new net minus this amount.')
                ->columnSpanFull(),
            Placeholder::make('balance_snapshot')
                ->label('Balance still due now')
                ->content('₹'.number_format($pending, 2))
                ->columnSpanFull(),
            TextInput::make('course_fee')
                ->label('Course fee')
                ->numeric()
                ->prefix('₹')
                ->default($feeStructure->course_fee)
                ->minValue(0)
                ->step(0.01)
                ->live(debounce: 300)
                ->afterStateUpdated($rebalanceInstallments)
                ->helperText('Increase or decrease the listed course fee. Misc charges stay as agreed at admission.')
                ->columnSpanFull(),
            Select::make('discount_mode')
                ->label('Discount change type')
                ->options([
                    'amount' => 'Fixed amount (₹)',
                    'percent' => 'Percentage of course fee (%)',
                ])
                ->default('amount')
                ->live()
                ->native(false),
            TextInput::make('discount_adjustment')
                ->label(fn (Get $get): string => ($get('discount_mode') ?? 'amount') === 'percent'
                    ? 'Additional discount (%)'
                    : 'Discount adjustment (₹)')
                ->numeric()
                ->default(0)
                ->step(0.01)
                ->helperText(fn (Get $get): string => ($get('discount_mode') ?? 'amount') === 'percent'
                    ? 'Enter % off the course fee. Applied on top of existing discount.'
                    : 'Positive = more discount. Negative = reduce discount. Net fee cannot fall below paid amount.')
                ->live(debounce: 300)
                ->afterStateUpdated($rebalanceInstallments),
            Hidden::make('additional_discount')
                ->default(0)
                ->dehydrated(false),
            Placeholder::make('new_net_fee_preview')
                ->label('After this change')
                ->content(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid): string {
                    $newNet = self::previewNet($feeStructure, $get, $miscTotal);
                    $newPending = round(max(0, $newNet - $paid), 2);
                    $newTotalDiscount = self::resolveDiscountAmount($feeStructure, [
                        'course_fee' => $get('course_fee'),
                        'discount_mode' => $get('discount_mode'),
                        'discount_adjustment' => $get('discount_adjustment'),
                    ]);

                    return 'New net ₹'.number_format($newNet, 2)
                        .' (was ₹'.number_format($currentNet, 2).')'
                        .' · Paid ₹'.number_format($paid, 2)
                        .' · New balance ₹'.number_format($newPending, 2)
                        .' · Total discount ₹'.number_format($newTotalDiscount, 2);
                })
                ->columnSpanFull(),
            Placeholder::make('reschedule_required_warning')
                ->label('')
                ->content('The balance due is changing — reschedule remaining installments is turned on so installment rows match the new amount.')
                ->visible(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid): bool {
                    $newNet = self::previewNet($feeStructure, $get, $miscTotal);

                    return round(max(0, $newNet - $paid), 2) > 0
                        && abs($newNet - $currentNet) > 0.01
                        && (bool) $get('reschedule_installments');
                })
                ->extraAttributes(['class' => 'text-sm font-medium text-amber-700 dark:text-amber-300'])
                ->columnSpanFull(),
            Textarea::make('reason')
                ->label('Reason for change')
                ->required()
                ->rows(3)
                ->maxLength(1000)
                ->helperText('Required before saving. Scroll down if needed to review installments, then click Save fee changes.')
                ->columnSpanFull(),
            Toggle::make('reschedule_installments')
                ->label('Reschedule remaining installments')
                ->helperText('Rebuild the payment schedule for the new balance still due. Paid installments are kept on record.')
                ->default($pending > 0)
                ->live()
                ->afterStateUpdated(function (bool $state, Get $get, Set $set) use ($feeStructure, $miscTotal, $rebalanceInstallments): void {
                    if (! $state) {
                        return;
                    }

                    $target = self::scheduleTarget($feeStructure, $get, $miscTotal);

                    if ($target <= 0) {
                        $set('installment_plan', []);

                        return;
                    }

                    $plan = $get('installment_plan') ?? [];

                    if ($plan === []) {
                        $set('installment_plan', [[
                            'label' => 'Balance due',
                            'amount' => (string) $target,
                            'due_date' => null,
                        ]]);

                        return;
                    }

                    $rebalanceInstallments($get, $set);
                })
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
                    $target = self::scheduleTarget($feeStructure, $get, $miscTotal);

                    return FeePlanCalculator::formatSummary($target, $get('installment_plan') ?? []);
                })
                ->visible(fn (Get $get): bool => (bool) $get('reschedule_installments'))
                ->columnSpanFull(),
            Placeholder::make('installment_unallocated_warning')
                ->label('')
                ->content(function (Get $get) use ($feeStructure, $miscTotal): string {
                    $target = self::scheduleTarget($feeStructure, $get, $miscTotal);

                    return FeePlanCalculator::unallocatedWarningMessage($target, $get('installment_plan') ?? []) ?? '';
                })
                ->visible(function (Get $get) use ($feeStructure, $miscTotal): bool {
                    if (! $get('reschedule_installments')) {
                        return false;
                    }

                    $target = self::scheduleTarget($feeStructure, $get, $miscTotal);

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
                    return self::scheduleTargetFromMounted(
                        $feeStructure,
                        $component->getLivewire()->mountedActionsData[0] ?? [],
                        $miscTotal,
                    );
                },
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing('installments');

        return [
            'course_fee' => $feeStructure->course_fee,
            'discount_mode' => 'amount',
            'discount_adjustment' => 0,
            'additional_discount' => 0,
            'reschedule_installments' => (float) $feeStructure->pending_amount > 0,
            'installment_plan' => self::pendingInstallmentPlan($feeStructure),
            'reason' => '',
        ];
    }

    /**
     * Map form state to values expected by FeeStructureService.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveForSave(FeeStructure $feeStructure, array $data): array
    {
        $courseFee = round((float) ($data['course_fee'] ?? $feeStructure->course_fee), 2);
        $discount = self::resolveDiscountAmount($feeStructure, $data);

        return array_merge($data, [
            'course_fee' => $courseFee,
            'discount_amount' => $discount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveDiscountAmount(FeeStructure $feeStructure, array $data): float
    {
        $courseFee = round((float) ($data['course_fee'] ?? $feeStructure->course_fee), 2);

        if (array_key_exists('discount_adjustment', $data) || array_key_exists('additional_discount', $data)) {
            $mode = (string) ($data['discount_mode'] ?? 'amount');
            $adjustment = (float) ($data['discount_adjustment'] ?? $data['additional_discount'] ?? 0);

            if ($mode === 'percent') {
                $adjustment = max(0, $adjustment);
                $delta = round($courseFee * $adjustment / 100, 2);
            } else {
                $delta = $adjustment;
            }

            return round(max(0, min((float) $feeStructure->discount_amount + $delta, $courseFee)), 2);
        }

        return round(max(0, (float) ($data['discount_amount'] ?? $feeStructure->discount_amount)), 2);
    }

    /**
     * Build installment rows that match the fee structure's current pending balance.
     *
     * @return list<array{label: string, amount: string, due_date: ?string}>
     */
    public static function pendingInstallmentPlan(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing('installments');

        $pendingTotal = round((float) $feeStructure->pending_amount, 2);

        if ($pendingTotal <= 0) {
            return [];
        }

        $unpaid = $feeStructure->installments
            ->filter(fn ($row): bool => (float) $row->pending_amount > 0.01)
            ->sortBy(fn ($row): array => [
                $row->due_date?->toDateString() ?? '9999-12-31',
                $row->sort_order,
                $row->id,
            ])
            ->values();

        $allocatedOnRows = round((float) $unpaid->sum('pending_amount'), 2);

        if ($unpaid->isEmpty()) {
            return [[
                'label' => 'Balance due',
                'amount' => (string) $pendingTotal,
                'due_date' => null,
            ]];
        }

        if (abs($allocatedOnRows - $pendingTotal) > 0.01) {
            $first = $unpaid->first();

            return [[
                'label' => $first?->label ?: 'Balance due',
                'amount' => (string) $pendingTotal,
                'due_date' => $first?->due_date?->toDateString(),
            ]];
        }

        return $unpaid->map(fn ($row): array => [
            'label' => $row->label,
            'amount' => (string) $row->pending_amount,
            'due_date' => $row->due_date?->toDateString(),
        ])->all();
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

    public static function previewNet(FeeStructure $feeStructure, Get $get, float $miscTotal): float
    {
        return self::previewNetFromMounted($feeStructure, [
            'course_fee' => $get('course_fee'),
            'discount_amount' => $get('discount_amount'),
            'additional_discount' => $get('additional_discount'),
        ], $miscTotal);
    }

    public static function scheduleTarget(FeeStructure $feeStructure, Get $get, float $miscTotal): float
    {
        $net = self::previewNet($feeStructure, $get, $miscTotal);

        return round(max(0, $net - (float) $feeStructure->paid_amount), 2);
    }

    /**
     * @param  array<string, mixed>  $mounted
     */
    public static function previewNetFromMounted(FeeStructure $feeStructure, array $mounted, float $miscTotal): float
    {
        $courseFee = max(0, (float) ($mounted['course_fee'] ?? $feeStructure->course_fee));
        $discount = self::resolveDiscountAmount($feeStructure, $mounted);

        return round($courseFee - $discount + $miscTotal, 2);
    }

    /**
     * @param  array<string, mixed>  $mounted
     */
    public static function scheduleTargetFromMounted(FeeStructure $feeStructure, array $mounted, float $miscTotal): float
    {
        $net = self::previewNetFromMounted($feeStructure, $mounted, $miscTotal);

        return round(max(0, $net - (float) $feeStructure->paid_amount), 2);
    }
}
