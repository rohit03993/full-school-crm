<?php

namespace App\Filament\Forms;

use App\Models\FeeStructure;
use App\Support\FeePlanCalculator;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
        $maxAdditionalDiscount = round(max(0, $currentNet - $paid), 2);

        $rebalanceInstallments = function (Get $get, Set $set) use ($feeStructure, $miscTotal): void {
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
            Hidden::make('course_fee')
                ->default($feeStructure->course_fee),
            TextInput::make('additional_discount')
                ->label('Give more discount now')
                ->numeric()
                ->prefix('₹')
                ->default(0)
                ->minValue(0)
                ->maxValue($maxAdditionalDiscount)
                ->step(0.01)
                ->helperText(
                    $maxAdditionalDiscount > 0
                        ? 'Enter extra discount from the current net (max ₹'.number_format($maxAdditionalDiscount, 2).' so net does not fall below paid).'
                        : 'Net fee already equals paid amount — no further discount possible.'
                )
                ->live(debounce: 300)
                ->afterStateUpdated($rebalanceInstallments)
                ->disabled($maxAdditionalDiscount <= 0),
            Placeholder::make('new_net_fee_preview')
                ->label('After this change')
                ->content(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $currentDiscount, $paid): string {
                    $additional = max(0, (float) ($get('additional_discount') ?? 0));
                    $newNet = self::previewNet($feeStructure, $get, $miscTotal);
                    $newPending = round(max(0, $newNet - $paid), 2);
                    $newTotalDiscount = round($currentDiscount + $additional, 2);

                    return 'New net ₹'.number_format($newNet, 2)
                        .' (was ₹'.number_format($currentNet, 2).')'
                        .' · Paid ₹'.number_format($paid, 2)
                        .' · New balance ₹'.number_format($newPending, 2)
                        .' · Total discount ₹'.number_format($newTotalDiscount, 2);
                })
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
        if (array_key_exists('additional_discount', $data)) {
            $additional = max(0, (float) ($data['additional_discount'] ?? 0));

            return round((float) $feeStructure->discount_amount + $additional, 2);
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
        if (array_key_exists('additional_discount', $mounted)) {
            $currentNet = round((float) $feeStructure->net_fee, 2);
            $additional = max(0, (float) ($mounted['additional_discount'] ?? 0));

            return round(max(0, $currentNet - $additional), 2);
        }

        $courseFee = max(0, (float) ($mounted['course_fee'] ?? $feeStructure->course_fee));
        $discount = max(0, (float) ($mounted['discount_amount'] ?? $feeStructure->discount_amount));

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
