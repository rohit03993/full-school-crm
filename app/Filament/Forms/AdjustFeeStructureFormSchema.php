<?php

namespace App\Filament\Forms;

use App\Enums\FeeMiscChargeStatus;
use App\Models\FeeMiscCharge;
use App\Models\FeeStructure;
use App\Support\FeePlanCalculator;
use App\Support\FeeSettings;
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
        $separateMiscTotal = $feeStructure->separateMiscChargesTotal();
        $separateMiscPending = $feeStructure->separateMiscChargesPendingTotal();
        $currentNet = round((float) $feeStructure->net_fee, 2);
        $currentDiscount = round((float) $feeStructure->discount_amount, 2);
        $studentCourseFee = round((float) $feeStructure->course_fee, 2);
        $format = fn (float $amount): string => FeePlanCalculator::formatRupeeAmount($amount);

        $onDiscountChanged = function (Get $get, Set $set) use ($feeStructure, $miscTotal): void {
            $mounted = self::mountedSliceFromGet($get);
            $newDiscount = self::resolveDiscountAmount($feeStructure, $mounted);

            if (abs($newDiscount - (float) $feeStructure->discount_amount) <= 0.01) {
                return;
            }

            $set('reschedule_installments', true);
            $target = self::scheduleTarget($feeStructure, $get, $miscTotal);

            if ($target <= 0) {
                $set('installment_plan', []);

                return;
            }

            $set('installment_plan', self::pendingInstallmentPlan($feeStructure, $target));
        };

        $tabs = [
            Tab::make('discount')
                ->label('Give discount')
                ->icon(Heroicon::OutlinedTag)
                ->schema([
                            Hidden::make('course_fee')
                                ->default($feeStructure->course_fee),
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
                                ->step(fn (Get $get): int|float => ($get('discount_mode') ?? 'amount') === 'percent' ? 0.01 : 1)
                                ->helperText(fn (Get $get): string => ($get('discount_mode') ?? 'amount') === 'percent'
                                    ? '% off course fee, added to existing discount.'
                                    : 'Whole rupees only. Positive adds discount. Negative reduces it.')
                                ->live(debounce: 300)
                                ->afterStateUpdated($onDiscountChanged),
                            Hidden::make('additional_discount')
                                ->default(0)
                                ->dehydrated(false),
                            Placeholder::make('new_net_fee_preview')
                                ->label('After change')
                                ->content(function (Get $get) use ($feeStructure, $miscTotal, $currentNet, $paid, $format): string {
                                    $mounted = self::mountedSliceFromGet($get);
                                    $newNet = self::previewNetFromMounted($feeStructure, $mounted, $miscTotal);
                                    $newPending = round(max(0, $newNet - $paid), 2);
                                    $newTotalDiscount = self::resolveDiscountAmount($feeStructure, $mounted);

                                    return 'Net ₹'.$format($newNet)
                                        .' (was ₹'.$format($currentNet).')'
                                        .' · Balance ₹'.$format($newPending)
                                        .' · Total discount ₹'.$format($newTotalDiscount);
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
                                ->afterStateUpdated(function (bool $state, Get $get, Set $set) use ($feeStructure, $miscTotal): void {
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
                                        $set('installment_plan', self::pendingInstallmentPlan($feeStructure, $target));
                                    }
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
                                ->label('Settled installments (read-only)')
                                ->content(fn (): HtmlString => new HtmlString(self::lockedInstallmentsHtml($feeStructure)))
                                ->visible(fn (): bool => self::lockedInstallmentsSummary($feeStructure) !== '')
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
                            AdmissionFeePlanFormSchema::configureAdjustFeeInstallmentRepeater(
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
        ];

        if (FeeSettings::onlineAllowanceGstEnabled()) {
            $tabs[] = Tab::make('cash_online')
                ->label('Cash / online')
                ->icon(Heroicon::OutlinedBanknotes)
                ->schema([
                    Placeholder::make('online_allowance_help')
                        ->label('Cash vs online agreement')
                        ->content('Split the net tuition fee. If the student pays more tuition via UPI/online than agreed, GST is charged on the excess only.')
                        ->columnSpanFull(),
                    TextInput::make('planned_cash_amount')
                        ->label('Agreed cash (₹)')
                        ->numeric()
                        ->minValue(0)
                        ->step(1)
                        ->default($feeStructure->planned_cash_amount)
                        ->live(debounce: 300),
                    TextInput::make('planned_online_amount')
                        ->label('Agreed online (₹)')
                        ->numeric()
                        ->minValue(0)
                        ->step(1)
                        ->default($feeStructure->planned_online_amount)
                        ->live(debounce: 300)
                        ->helperText(fn (Get $get): string => 'Cash plus online must equal net tuition (₹'
                            .$format(self::previewNet($feeStructure, $get, $miscTotal)).').'),
                    Placeholder::make('online_allowance_preview')
                        ->label('Split check')
                        ->content(function (Get $get) use ($feeStructure, $miscTotal, $format): string {
                            $net = self::previewNet($feeStructure, $get, $miscTotal);
                            $cash = round((float) ($get('planned_cash_amount') ?? 0), 2);
                            $online = round((float) ($get('planned_online_amount') ?? 0), 2);
                            $total = round($cash + $online, 2);

                            if ($cash <= 0 && $online <= 0) {
                                return 'Enter cash and online amounts that add up to net tuition.';
                            }

                            if (abs($total - $net) <= 0.01) {
                                return 'Cash ₹'.$format($cash).' + Online ₹'.$format($online).' = Net ₹'.$format($net);
                            }

                            return 'Cash ₹'.$format($cash).' + Online ₹'.$format($online).' = ₹'.$format($total)
                                .' · Net tuition is ₹'.$format($net).' — adjust the split.';
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2);
        }

        $tabs[] = Tab::make('misc')
            ->label('Misc charges')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->schema([
                Placeholder::make('existing_misc_charges')
                    ->label('Current charges')
                    ->content(fn (): HtmlString => new HtmlString(self::separateMiscChargesHtml($feeStructure)))
                    ->columnSpanFull(),
                Repeater::make('new_misc_charges')
                    ->label('Add new charges')
                    ->helperText('Exam fees, materials, etc. — collected separately from tuition installments.')
                    ->schema(AddMiscChargeFormSchema::fields())
                    ->addActionLabel('Add charge')
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->columnSpanFull(),
            ]);

        return [
            Placeholder::make('fee_snapshot')
                ->label('')
                ->content(new HtmlString(
                    '<div class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">'
                    .'<div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Net tuition fee</p>'
                    .'<p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">₹'.$format($currentNet).'</p>'
                    .'<p class="mt-0.5 text-xs text-gray-500">Course ₹'.$format($studentCourseFee)
                    .' · Discount ₹'.$format($currentDiscount)
                    .($miscTotal > 0 ? ' · In plan misc ₹'.$format($miscTotal) : '')
                    .'</p></div>'
                    .'<div class="rounded-lg bg-violet-50 px-3 py-2 dark:bg-violet-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-violet-800 dark:text-violet-300">Misc charges</p>'
                    .'<p class="mt-0.5 text-base font-bold text-violet-950 dark:text-violet-50">₹'.$format($separateMiscTotal).'</p>'
                    .'<p class="mt-0.5 text-xs text-violet-800/80 dark:text-violet-200">Pending ₹'.$format($separateMiscPending).' · paid separately</p></div>'
                    .'<div class="rounded-lg bg-emerald-50 px-3 py-2 dark:bg-emerald-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Tuition paid</p>'
                    .'<p class="mt-0.5 text-base font-bold text-emerald-800 dark:text-emerald-200">₹'.$format($paid).'</p>'
                    .'<p class="mt-0.5 text-xs text-emerald-700/80 dark:text-emerald-300/80">Already collected — fixed</p></div>'
                    .'<div class="rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-500/10">'
                    .'<p class="text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">Tuition balance</p>'
                    .'<p class="mt-0.5 text-base font-bold text-amber-900 dark:text-amber-100">₹'.$format($pending).'</p>'
                    .'<p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-200/80">Pending installments</p></div>'
                    .'</div>'
                ))
                ->columnSpanFull(),
            Tabs::make('adjustFeeTabs')
                ->tabs($tabs)
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
        $currentPending = round((float) $feeStructure->pending_amount, 2);

        return [
            'course_fee' => $studentCourseFee,
            'discount_mode' => 'amount',
            'discount_adjustment' => 0,
            'additional_discount' => 0,
            'reschedule_installments' => $currentPending > 0,
            'installment_plan' => self::pendingInstallmentPlan($feeStructure),
            'new_misc_charges' => [],
            'planned_cash_amount' => $feeStructure->planned_cash_amount,
            'planned_online_amount' => $feeStructure->planned_online_amount,
            'reason' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, amount: float, due_date: ?string}>
     */
    public static function resolveNewMiscCharges(array $data): array
    {
        $rows = [];

        foreach ($data['new_misc_charges'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $dueDate = filled($row['due_date'] ?? null) ? (string) $row['due_date'] : null;

            if ($label === '' && $amount <= 0) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'amount' => $amount,
                'due_date' => $dueDate,
            ];
        }

        return $rows;
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
            'amount' => FeePlanCalculator::normalizeRowAmount($row->pending_amount),
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
        return self::lockedInstallmentsSummary($feeStructure, settledOnly: true);
    }

    public static function lockedInstallmentsSummary(FeeStructure $feeStructure, bool $settledOnly = false): string
    {
        $feeStructure->loadMissing('installments');

        $lines = $feeStructure->installments
            ->filter(function ($row) use ($settledOnly): bool {
                $paid = (float) $row->paid_amount;
                $pending = (float) $row->pending_amount;

                if ($settledOnly) {
                    return $paid > 0 && $pending <= 0.01;
                }

                return $paid > 0;
            })
            ->sortBy(fn ($row): array => [
                $row->due_date?->toDateString() ?? '0000-01-01',
                $row->sort_order,
                $row->id,
            ])
            ->map(function ($row) use ($settledOnly): string {
                $due = $row->due_date?->format('d M Y') ?? 'TBD';
                $label = FeePlanCalculator::displayInstallmentLabel((string) $row->label, (int) $row->sort_order);
                $paid = FeePlanCalculator::formatRupeeAmount((float) $row->paid_amount);
                $total = FeePlanCalculator::formatRupeeAmount((float) $row->amount);
                $pending = round((float) $row->pending_amount, 2);

                if ($settledOnly || $pending <= 0.01) {
                    return sprintf('%s — due %s — paid ₹%s of ₹%s', $label, $due, $paid, $total);
                }

                return sprintf(
                    '%s — due %s — paid ₹%s of ₹%s · ₹%s still due (edit pending rows below)',
                    $label,
                    $due,
                    $paid,
                    $total,
                    FeePlanCalculator::formatRupeeAmount($pending),
                );
            })
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    public static function paidInstallmentsHtml(FeeStructure $feeStructure): string
    {
        return self::lockedInstallmentsHtml($feeStructure, settledOnly: true);
    }

    public static function lockedInstallmentsHtml(FeeStructure $feeStructure, bool $settledOnly = false): string
    {
        $summary = self::lockedInstallmentsSummary($feeStructure, $settledOnly);

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

    public static function separateMiscChargesHtml(FeeStructure $feeStructure): string
    {
        $feeStructure->loadMissing('miscCharges');

        $charges = $feeStructure->separateMiscCharges()
            ->reject(fn (FeeMiscCharge $charge): bool => $charge->status === FeeMiscChargeStatus::Cancelled)
            ->values();

        if ($charges->isEmpty()) {
            return '<p class="text-sm text-gray-500 dark:text-gray-400">No separate misc charges yet. Add rows below.</p>';
        }

        $lines = $charges->map(function (FeeMiscCharge $charge): string {
            $pending = $charge->pendingAmount();
            $status = $charge->status->label();
            $due = $charge->due_date?->format('d M Y');

            $line = sprintf(
                '%s — ₹%s total',
                $charge->label,
                FeePlanCalculator::formatRupeeAmount((float) $charge->amount),
            );

            if ((float) $charge->paid_amount > 0) {
                $line .= ' · paid ₹'.FeePlanCalculator::formatRupeeAmount((float) $charge->paid_amount);
            }

            if ($pending > 0) {
                $line .= ' · pending ₹'.FeePlanCalculator::formatRupeeAmount($pending);
            }

            $line .= ' · '.$status;

            if ($due) {
                $line .= ' · due '.$due;
            }

            return $line;
        });

        return '<div class="space-y-1 text-sm">'
            .$lines->map(fn (string $line): string => '<p class="text-gray-700 dark:text-gray-300">'.e($line).'</p>')->implode('')
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
