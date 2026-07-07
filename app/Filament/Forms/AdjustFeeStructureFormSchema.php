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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class AdjustFeeStructureFormSchema
{
    public const INSTALLMENT_ONLY_REASON = 'Installment schedule updated (no fee or discount change).';

    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing(['miscCharges', 'installments', 'enrollment.course']);

        $paid = round((float) $feeStructure->paid_amount, 2);
        $pending = round((float) $feeStructure->pending_amount, 2);
        $miscTotal = $feeStructure->miscChargesTotal();
        $currentNet = round((float) $feeStructure->net_fee, 2);
        $currentDiscount = round((float) $feeStructure->discount_amount, 2);
        $studentCourseFee = round((float) $feeStructure->course_fee, 2);
        $catalogCourseFee = round((float) ($feeStructure->enrollment?->course?->fee ?? 0), 2);
        $catalogDiffers = $catalogCourseFee > 0 && abs($catalogCourseFee - $studentCourseFee) > 0.01;

        $rebalanceInstallments = function (Get $get, Set $set) use ($feeStructure, $miscTotal, $studentCourseFee): void {
            $mounted = self::mountedSliceFromGet($get);
            $newDiscount = self::resolveDiscountAmount($feeStructure, $mounted);

            if (abs($newDiscount - (float) $feeStructure->discount_amount) > 0.01
                || round((float) ($get('course_fee') ?? $studentCourseFee), 2) !== $studentCourseFee) {
                $set('reschedule_installments', true);
            }

            if (! (bool) $get('reschedule_installments')) {
                return;
            }

            $target = self::scheduleTarget($feeStructure, $get, $miscTotal);
            $plan = $get('installment_plan') ?? [];

            if ($plan === [] && $target > 0) {
                $set('installment_plan', FeePlanCalculator::singleFullFeeRow($target));

                return;
            }

            if ($plan !== []) {
                $set('installment_plan', FeePlanCalculator::rebalancePlanToTarget($plan, $target));
            }
        };

        return [
            Placeholder::make('fee_snapshot')
                ->label('')
                ->content(new HtmlString(
                    '<div class="grid gap-3 text-sm sm:grid-cols-3">'
                    .'<div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Net fee</p>'
                    .'<p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">₹'.number_format($currentNet, 2).'</p>'
                    .'<p class="mt-0.5 text-xs text-gray-500">Course ₹'.number_format($studentCourseFee, 2)
                    .' · Discount ₹'.number_format($currentDiscount, 2)
                    .($miscTotal > 0 ? ' · Misc ₹'.number_format($miscTotal, 2) : '')
                    .'</p></div>'
                    .'<div class="rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Paid</p>'
                    .'<p class="mt-0.5 text-base font-bold text-emerald-800 dark:text-emerald-200">₹'.number_format($paid, 2).'</p>'
                    .'<p class="mt-0.5 text-xs text-emerald-700/80 dark:text-emerald-300/80">Already collected — fixed</p></div>'
                    .'<div class="rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">Balance due</p>'
                    .'<p class="mt-0.5 text-base font-bold text-amber-900 dark:text-amber-100">₹'.number_format($pending, 2).'</p>'
                    .'<p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-200/80">Pending installments</p></div>'
                    .'</div>'
                ))
                ->columnSpanFull(),
            Placeholder::make('catalog_fee_hint')
                ->label('')
                ->content(new HtmlString(
                    '<p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">'
                    .'Course catalog fee is now <strong>₹'.number_format($catalogCourseFee, 2).'</strong> '
                    .'but this student is on <strong>₹'.number_format($studentCourseFee, 2).'</strong>. '
                    .'Use <strong>Apply catalog fee</strong> in the footer or open the discount tab.</p>'
                ))
                ->visible($catalogDiffers)
                ->columnSpanFull(),
            Tabs::make('adjustFeeTabs')
                ->tabs([
                    Tab::make('discount')
                        ->label('Give discount')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('discount_mode')
                                ->label('Type')
                                ->options([
                                    'amount' => 'Fixed amount (₹)',
                                    'percent' => 'Percentage (%)',
                                ])
                                ->default('amount')
                                ->live()
                                ->native(false),
                            TextInput::make('discount_adjustment')
                                ->label(fn (Get $get): string => ($get('discount_mode') ?? 'amount') === 'percent'
                                    ? 'Extra discount (%)'
                                    : 'Extra discount (₹)')
                                ->numeric()
                                ->default(0)
                                ->step(0.01)
                                ->helperText(fn (Get $get): string => ($get('discount_mode') ?? 'amount') === 'percent'
                                    ? '% off course fee, added to existing discount.'
                                    : 'Positive adds discount. Negative reduces it.')
                                ->live(debounce: 300)
                                ->afterStateUpdated($rebalanceInstallments),
                            Hidden::make('additional_discount')
                                ->default(0)
                                ->dehydrated(false),
                            Placeholder::make('new_net_fee_preview')
                                ->label('After change')
                                ->content(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid): string {
                                    $mounted = self::mountedSliceFromGet($get);
                                    $newNet = self::previewNetFromMounted($feeStructure, $mounted, $miscTotal);
                                    $newPending = round(max(0, $newNet - $paid), 2);
                                    $newTotalDiscount = self::resolveDiscountAmount($feeStructure, $mounted);

                                    return 'Net ₹'.number_format($newNet, 2)
                                        .' (was ₹'.number_format($currentNet, 2).')'
                                        .' · Balance ₹'.number_format($newPending, 2)
                                        .' · Total discount ₹'.number_format($newTotalDiscount, 2);
                                })
                                ->columnSpanFull(),
                            Textarea::make('reason')
                                ->label('Reason for discount')
                                ->required(fn (Get $get): bool => self::requiresReasonFromGet($feeStructure, self::mountedSliceFromGet($get)))
                                ->visible(fn (Get $get): bool => self::requiresReasonFromGet($feeStructure, self::mountedSliceFromGet($get)))
                                ->rows(2)
                                ->maxLength(1000)
                                ->placeholder('e.g. Sibling discount, staff concession')
                                ->columnSpanFull(),
                            Placeholder::make('installments_tab_hint')
                                ->label('')
                                ->content('Balance changed — open the Installments tab to review the pending schedule.')
                                ->visible(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid): bool {
                                    if (! (bool) $get('reschedule_installments')) {
                                        return false;
                                    }

                                    $newNet = self::previewNet($feeStructure, $get, $miscTotal);

                                    return round(max(0, $newNet - $paid), 2) > 0
                                        && abs($newNet - $currentNet) > 0.01;
                                })
                                ->extraAttributes(['class' => 'text-sm font-medium text-primary-600 dark:text-primary-400'])
                                ->columnSpanFull(),
                            Toggle::make('edit_course_fee')
                                ->label('Change listed course fee')
                                ->helperText('Only when this student’s agreed fee should differ from what is on file.')
                                ->live()
                                ->default($catalogDiffers)
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
                                ->visible(fn (Get $get): bool => (bool) $get('edit_course_fee'))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                    Tab::make('installments')
                        ->label('Installments')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->schema([
                            Toggle::make('reschedule_installments')
                                ->label('Reschedule remaining installments')
                                ->helperText('Turn on to edit the payment schedule for the balance still due. Settled installments stay locked.')
                                ->default(false)
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
                                        $set('installment_plan', FeePlanCalculator::singleFullFeeRow($target));

                                        return;
                                    }

                                    $rebalanceInstallments($get, $set);
                                })
                                ->columnSpanFull(),
                            Placeholder::make('reschedule_required_warning')
                                ->label('')
                                ->content('Fee or discount changed — review pending rows so they total the new balance.')
                                ->visible(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid): bool {
                                    $newNet = self::previewNet($feeStructure, $get, $miscTotal);

                                    return round(max(0, $newNet - $paid), 2) > 0
                                        && abs($newNet - $currentNet) > 0.01
                                        && (bool) $get('reschedule_installments');
                                })
                                ->extraAttributes(['class' => 'text-sm font-medium text-amber-700 dark:text-amber-300'])
                                ->columnSpanFull(),
                            Placeholder::make('paid_installments_snapshot')
                                ->label('Settled installments')
                                ->content(fn (): HtmlString => new HtmlString(self::paidInstallmentsHtml($feeStructure)))
                                ->visible(fn (): bool => self::paidInstallmentsSummary($feeStructure) !== '')
                                ->columnSpanFull(),
                            Placeholder::make('installment_allocation_summary')
                                ->label('Allocation')
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
                                    ->label('Pending installments')
                                    ->columns(3)
                                    ->columnSpanFull()
                                    ->defaultItems(0)
                                    ->visible(fn (Get $get): bool => (bool) $get('reschedule_installments'))
                                    ->live(),
                                function (Repeater $component) use ($feeStructure, $miscTotal): float {
                                    $mounted = $component->getLivewire()->mountedActionsData[0] ?? [];

                                    return self::scheduleTargetFromMounted(
                                        $feeStructure,
                                        is_array($mounted) ? $mounted : [],
                                        $miscTotal,
                                    );
                                },
                            ),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(FeeStructure $feeStructure): array
    {
        $feeStructure->loadMissing(['installments', 'enrollment.course', 'miscCharges']);

        $studentCourseFee = round((float) $feeStructure->course_fee, 2);
        $catalogCourseFee = round((float) ($feeStructure->enrollment?->course?->fee ?? 0), 2);
        $catalogDiffers = $catalogCourseFee > 0 && abs($catalogCourseFee - $studentCourseFee) > 0.01;
        $courseFeeForForm = $catalogDiffers ? $catalogCourseFee : $studentCourseFee;
        $discount = round((float) $feeStructure->discount_amount, 2);
        $miscTotal = $feeStructure->miscChargesTotal();
        $paid = round((float) $feeStructure->paid_amount, 2);
        $projectedPending = round(max(0, $courseFeeForForm - $discount + $miscTotal - $paid), 2);
        $currentPending = round((float) $feeStructure->pending_amount, 2);
        $balanceWillChange = abs($projectedPending - $currentPending) > 0.01;

        return [
            'course_fee' => $courseFeeForForm,
            'edit_course_fee' => $catalogDiffers,
            'discount_mode' => 'amount',
            'discount_adjustment' => 0,
            'additional_discount' => 0,
            'reschedule_installments' => $catalogDiffers || $balanceWillChange,
            'installment_plan' => self::pendingInstallmentPlan(
                $feeStructure,
                $catalogDiffers || $balanceWillChange ? $projectedPending : null,
            ),
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
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '' && ! self::requiresReasonFromMounted($feeStructure, $data)) {
            $reason = self::INSTALLMENT_ONLY_REASON;
        }

        return array_merge($data, [
            'course_fee' => $courseFee,
            'discount_amount' => $discount,
            'reason' => $reason,
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
     * @param  array<string, mixed>  $data
     */
    public static function requiresReasonFromGet(FeeStructure $feeStructure, array $data): bool
    {
        return self::requiresReasonFromMounted($feeStructure, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requiresReasonFromMounted(FeeStructure $feeStructure, array $data): bool
    {
        $newDiscount = self::resolveDiscountAmount($feeStructure, $data);

        return abs($newDiscount - (float) $feeStructure->discount_amount) > 0.01;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mountedSliceFromGet(Get $get): array
    {
        return [
            'course_fee' => $get('course_fee'),
            'discount_mode' => $get('discount_mode'),
            'discount_adjustment' => $get('discount_adjustment'),
            'additional_discount' => $get('additional_discount'),
        ];
    }

    /**
     * Build installment rows that match the fee structure's current pending balance.
     *
     * @return list<array{label: string, amount: string, due_date: ?string}>
     */
    public static function pendingInstallmentPlan(FeeStructure $feeStructure, ?float $pendingOverride = null): array
    {
        $feeStructure->loadMissing('installments');

        $pendingTotal = round($pendingOverride ?? (float) $feeStructure->pending_amount, 2);

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

        if ($unpaid->isEmpty()) {
            return FeePlanCalculator::singleFullFeeRow($pendingTotal);
        }

        $rows = $unpaid->map(fn ($row): array => [
            'label' => FeePlanCalculator::displayInstallmentLabel((string) $row->label, (int) $row->sort_order),
            'amount' => (string) $row->pending_amount,
            'due_date' => $row->due_date?->toDateString(),
        ])->all();

        $allocatedOnRows = round((float) $unpaid->sum('pending_amount'), 2);

        if (abs($allocatedOnRows - $pendingTotal) > 0.01) {
            return FeePlanCalculator::rebalancePlanToTarget($rows, $pendingTotal);
        }

        return $rows;
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
                $label = FeePlanCalculator::displayInstallmentLabel((string) $row->label, (int) $row->sort_order);

                return sprintf(
                    '%s — due %s — paid ₹%s of ₹%s',
                    $label,
                    $due,
                    number_format((float) $row->paid_amount, 2),
                    number_format((float) $row->amount, 2),
                );
            })
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    public static function paidInstallmentsHtml(FeeStructure $feeStructure): string
    {
        $summary = self::paidInstallmentsSummary($feeStructure);

        if ($summary === '') {
            return '';
        }

        return '<div class="space-y-1 text-sm">'
            .collect(explode("\n", $summary))
                ->filter()
                ->map(fn (string $line): string => '<p class="flex items-start gap-2"><span class="text-emerald-600">✓</span><span>'.e($line).'</span></p>')
                ->implode('')
            .'</div>';
    }

    public static function previewNet(FeeStructure $feeStructure, Get $get, float $miscTotal): float
    {
        return self::previewNetFromMounted($feeStructure, self::mountedSliceFromGet($get), $miscTotal);
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
