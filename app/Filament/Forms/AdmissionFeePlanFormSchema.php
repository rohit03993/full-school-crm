<?php

namespace App\Filament\Forms;

use App\Models\Course;
use App\Support\FeePlanCalculator;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;

class AdmissionFeePlanFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(): array
    {
        return [
            Repeater::make('misc_fees')
                ->label('Miscellaneous charges')
                ->helperText('Optional extras added on top of course fee (transport, exam fee, etc.).')
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Transport, Exam fee'),
                    TextInput::make('amount')
                        ->label('Amount (₹)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->step(0.01),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
                ->live()
                ->afterStateUpdated(function (mixed $state, callable $set, Get $get): void {
                    ConvertToAdmissionFormSchema::syncFeeDisplays($set, $get('course_id'), $get('discount_amount'), $state);
                }),
            Toggle::make('use_installment_plan')
                ->label('Split into installments')
                ->helperText('When off, the full net fee is due as one installment after enrollment.')
                ->live()
                ->afterStateUpdated(function (mixed $state, callable $set, Get $get): void {
                    if (! $state) {
                        return;
                    }

                    $net = self::resolveNetFee($get);

                    if ($net <= 0) {
                        return;
                    }

                    if (empty($get('installment_plan'))) {
                        self::applyCourseTemplateIfNeeded($set, $get, $net);
                    }
                })
                ->columnSpanFull(),
            Placeholder::make('installment_allocation_summary')
                ->label('Installment allocation')
                ->content(fn (Get $get): string => FeePlanCalculator::formatSummary(
                    self::resolveNetFee($get),
                    $get('installment_plan') ?? [],
                ))
                ->visible(fn (Get $get): bool => (bool) $get('use_installment_plan'))
                ->columnSpanFull(),
            Placeholder::make('installment_unallocated_warning')
                ->label('')
                ->content(fn (Get $get): string => FeePlanCalculator::unallocatedWarningMessage(
                    self::resolveNetFee($get),
                    $get('installment_plan') ?? [],
                ) ?? '')
                ->visible(function (Get $get): bool {
                    if (! $get('use_installment_plan')) {
                        return false;
                    }

                    $net = self::resolveNetFee($get);

                    return FeePlanCalculator::unallocatedWarningMessage($net, $get('installment_plan') ?? []) !== null;
                })
                ->extraAttributes(['class' => 'text-sm font-medium text-danger-600 dark:text-danger-400'])
                ->columnSpanFull(),
            self::configureInstallmentRepeater(
                Repeater::make('installment_plan')
                    ->label('Installment schedule')
                    ->columns(3)
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->visible(fn (Get $get): bool => (bool) $get('use_installment_plan'))
                    ->live(),
                fn (Repeater $component): float => self::resolveNetFeeFromMountedAction($component),
            ),
        ];
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    public static function installmentRowSchema(): array
    {
        return [
            TextInput::make('label')
                ->required()
                ->maxLength(100)
                ->placeholder('Installment 1'),
            TextInput::make('amount')
                ->label('Amount (₹)')
                ->numeric()
                ->required()
                ->minValue(0.01)
                ->step(0.01)
                ->live(debounce: 300),
            DatePicker::make('due_date')
                ->label('Due date')
                ->native(false)
                ->live()
                ->afterStateUpdated(function (DatePicker $component): void {
                    self::resortInstallmentRepeater($component);
                })
                ->helperText('Rows reorder by due date. Custom labels are kept.'),
        ];
    }

    public static function findParentRepeater(Field $component): ?Repeater
    {
        $parent = $component->getContainer()->getParentComponent();

        while ($parent && ! $parent instanceof Repeater) {
            $parent = $parent->getParentComponent();
        }

        return $parent instanceof Repeater ? $parent : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function installmentPlanRowsFromRepeater(Field $component): array
    {
        $repeater = self::findParentRepeater($component);

        if (! $repeater) {
            return [];
        }

        return array_values($repeater->getState() ?? []);
    }

    public static function resortInstallmentRepeater(Field $component): void
    {
        $repeater = self::findParentRepeater($component);

        if (! $repeater) {
            return;
        }

        $items = $repeater->getRawState();

        if (! is_array($items) || $items === []) {
            return;
        }

        $repeater->rawState(FeePlanCalculator::sortAndRenumberRepeaterItems($items));
        $repeater->callAfterStateUpdated();
    }

    public static function repeaterItemIndex(Field $component): int
    {
        $container = $component->getContainer();
        $itemPath = (string) $container->getStatePath();
        $segments = explode('.', $itemPath);
        array_pop($segments);
        $itemKey = array_pop($segments);

        $parent = $container->getParentComponent();

        while ($parent && ! $parent instanceof Repeater) {
            $parent = $parent->getParentComponent();
        }

        if (! $parent instanceof Repeater) {
            return 0;
        }

        $keys = array_keys($parent->getState() ?? []);
        $index = array_search($itemKey, $keys, true);

        return $index === false ? 0 : (int) $index;
    }

    public static function autoFillEmptyRowInRepeater(Field $component, Closure $resolveTarget): void
    {
        $repeater = self::findParentRepeater($component);

        if (! $repeater) {
            return;
        }

        $items = $repeater->getRawState();

        if (! is_array($items)) {
            return;
        }

        $rows = array_values($repeater->getState() ?? []);
        $filled = FeePlanCalculator::autoFillSingleEmptyRow($rows, $resolveTarget($repeater));

        if ($filled === $rows) {
            return;
        }

        $keys = array_keys($items);
        $newItems = [];

        foreach ($filled as $index => $row) {
            $newItems[$keys[$index] ?? $index] = $row;
        }

        $repeater->rawState($newItems);
    }

    /**
     * @return array<int, \Filament\Forms\Components\Field>
     */
    public static function installmentRowSchemaForRepeater(Closure $resolveTarget): array
    {
        $schema = self::installmentRowSchema();

        foreach ($schema as $field) {
            if ($field->getName() !== 'amount') {
                continue;
            }

            $field->afterStateUpdated(function (mixed $state, Field $component) use ($resolveTarget): void {
                self::autoFillEmptyRowInRepeater($component, $resolveTarget);
            });
        }

        return $schema;
    }

    public static function configureInstallmentRepeater(Repeater $repeater, Closure $resolveTarget): Repeater
    {
        return $repeater
            ->schema(self::installmentRowSchemaForRepeater($resolveTarget))
            ->helperText('New rows default to the remaining balance and the next due date (+1 month). One empty row auto-fills the remaining balance.')
            ->addActionLabel('Add row')
            ->addAction(function (Action $action) use ($resolveTarget): Action {
                return $action->action(function (Repeater $component) use ($resolveTarget): void {
                    $newUuid = $component->generateUuid();
                    $items = $component->getRawState();
                    $existing = array_values($component->getState() ?? []);
                    $target = $resolveTarget($component);
                    $newRow = FeePlanCalculator::newInstallmentRow($existing, $target, count($existing));

                    if ($newUuid) {
                        $items[$newUuid] = $newRow;
                    } else {
                        $items[] = $newRow;
                    }

                    $component->rawState($items);
                    $component->collapsed(false, shouldMakeComponentCollapsible: false);
                    $component->callAfterStateUpdated();
                    $component->shouldPartiallyRenderAfterActionsCalled() ? $component->partiallyRender() : null;
                });
            });
    }

    public static function resolveNetFee(Get $get): float
    {
        return self::resolveNetFeeFromArray([
            'course_id' => $get('course_id'),
            'discount_amount' => $get('discount_amount'),
            'misc_fees' => $get('misc_fees'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolveNetFeeFromArray(array $data): float
    {
        $courseId = $data['course_id'] ?? null;
        $discount = max(0, (float) ($data['discount_amount'] ?? 0));
        $miscTotal = FeePlanCalculator::sumAmounts($data['misc_fees'] ?? []);

        if (! $courseId) {
            return 0.0;
        }

        $course = Course::query()->find($courseId);

        if (! $course) {
            return 0.0;
        }

        return round(max(0, (float) $course->fee - $discount + $miscTotal), 2);
    }

    public static function resolveNetFeeFromMountedAction(Repeater $component): float
    {
        $livewire = $component->getLivewire();
        $mounted = $livewire->mountedActionsData[0] ?? [];

        return self::resolveNetFeeFromArray(is_array($mounted) ? $mounted : []);
    }

    public static function fillBalanceOnLastRow(callable $set, Get $get): void
    {
        $plan = $get('installment_plan') ?? [];
        $net = self::resolveNetFee($get);

        if ($plan === [] || $net <= 0) {
            return;
        }

        $set('installment_plan', FeePlanCalculator::fillBalanceOnLastRow($plan, $net));
    }

    public static function suggestInstallmentPlan(callable $set, Get $get): void
    {
        $net = self::resolveNetFee($get);

        if ($net <= 0) {
            return;
        }

        self::applyCourseTemplateIfNeeded($set, $get, $net);

        if (! empty($get('installment_plan'))) {
            $set('use_installment_plan', true);

            return;
        }

        $set('installment_plan', FeePlanCalculator::defaultTwoPartPlan($net));
        $set('use_installment_plan', true);
    }

    public static function applyCourseTemplateIfNeeded(callable $set, Get $get, ?float $netFee = null): void
    {
        $courseId = $get('course_id');

        if (! $courseId) {
            return;
        }

        $course = Course::query()->with('installmentTemplates')->find($courseId);

        if (! $course) {
            return;
        }

        $net = $netFee ?? self::resolveNetFee($get);
        $rows = FeePlanCalculator::planFromCourseTemplates($course, $net);

        if ($rows !== []) {
            $set('installment_plan', $rows);
        } elseif (empty($get('installment_plan'))) {
            $set('installment_plan', [FeePlanCalculator::singleFullFeeRow($net)]);
        }
    }
}
