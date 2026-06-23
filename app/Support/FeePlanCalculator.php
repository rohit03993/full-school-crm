<?php

namespace App\Support;

class FeePlanCalculator
{
    /**
     * @param  array<int, array{amount?: mixed}>  $rows
     */
    public static function sumAmounts(array $rows): float
    {
        return round(collect($rows)->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);
    }

    public static function remaining(float $targetTotal, array $rows): float
    {
        return round($targetTotal - self::sumAmounts($rows), 2);
    }

    public static function isFullyAllocated(float $targetTotal, array $rows): bool
    {
        return abs(self::remaining($targetTotal, $rows)) <= 0.01;
    }

    public static function formatSummary(float $targetTotal, array $rows): string
    {
        $allocated = self::sumAmounts($rows);
        $remaining = self::remaining($targetTotal, $rows);

        $lines = [
            'To schedule: ₹'.number_format($targetTotal, 2),
            'Allocated: ₹'.number_format($allocated, 2),
        ];

        if (abs($remaining) <= 0.01) {
            $lines[] = 'Remaining: ₹0.00 ✓';
        } elseif ($remaining > 0) {
            $lines[] = 'Remaining: ₹'.number_format($remaining, 2);
        } else {
            $lines[] = 'Over by: ₹'.number_format(abs($remaining), 2);
        }

        return implode(' · ', $lines);
    }

    public static function unallocatedWarningMessage(float $targetTotal, array $rows): ?string
    {
        if ($targetTotal <= 0) {
            return null;
        }

        $remaining = self::remaining($targetTotal, $rows);

        if (abs($remaining) <= 0.01) {
            return null;
        }

        if ($remaining > 0) {
            return '₹'.number_format($remaining, 2).' still unallocated — add a row or use Fill balance on last row.';
        }

        return 'Installments exceed the balance by ₹'.number_format(abs($remaining), 2).' — reduce amounts.';
    }

    public static function installmentLabel(int $number): string
    {
        return 'Installment '.$number;
    }

    /**
     * @param  array<int, array{label?: string, amount?: mixed, due_date?: ?string}>  $rows
     * @return array<int, array{label?: string, amount?: mixed, due_date?: ?string}>
     */
    public static function sortAndRenumberInstallmentPlan(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $rows = array_values($rows);

        usort($rows, function (array $a, array $b): int {
            $dateA = $a['due_date'] ?? null;
            $dateB = $b['due_date'] ?? null;

            if (! $dateA && ! $dateB) {
                return 0;
            }

            if (! $dateA) {
                return 1;
            }

            if (! $dateB) {
                return -1;
            }

            return strcmp((string) $dateA, (string) $dateB);
        });

        foreach ($rows as $index => &$row) {
            $row['label'] = self::installmentLabel($index + 1);
        }
        unset($row);

        return $rows;
    }

    /**
     * When exactly one row has no amount, fill it with the remaining balance.
     *
     * @param  array<int, array{amount?: mixed}>  $rows
     * @return array<int, array{amount?: mixed}>
     */
    public static function autoFillSingleEmptyRow(array $rows, float $targetTotal): array
    {
        if ($targetTotal <= 0 || $rows === []) {
            return $rows;
        }

        $emptyIndices = [];

        foreach ($rows as $index => $row) {
            if ((float) ($row['amount'] ?? 0) <= 0) {
                $emptyIndices[] = $index;
            }
        }

        if (count($emptyIndices) !== 1) {
            return $rows;
        }

        $remaining = self::remaining($targetTotal, $rows);

        if (abs($remaining) <= 0.01) {
            return $rows;
        }

        $rows[$emptyIndices[0]]['amount'] = (string) round(max(0, $remaining), 2);

        return $rows;
    }

    /**
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    public static function sortAndRenumberRepeaterItems(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $pairs = [];

        foreach ($items as $uuid => $row) {
            $pairs[] = [
                'uuid' => (string) $uuid,
                'row' => is_array($row) ? $row : [],
            ];
        }

        usort($pairs, function (array $a, array $b): int {
            $dateA = $a['row']['due_date'] ?? null;
            $dateB = $b['row']['due_date'] ?? null;

            if (! $dateA && ! $dateB) {
                return 0;
            }

            if (! $dateA) {
                return 1;
            }

            if (! $dateB) {
                return -1;
            }

            return strcmp((string) $dateA, (string) $dateB);
        });

        $sorted = [];

        foreach ($pairs as $index => $pair) {
            $row = $pair['row'];
            $row['label'] = self::installmentLabel($index + 1);
            $sorted[$pair['uuid']] = $row;
        }

        return $sorted;
    }

    /**
     * @param  array<int, array{due_date?: ?string}>  $rows
     */
    public static function minDueDateForRowAtIndex(array $rows, int $index): ?\Illuminate\Support\Carbon
    {
        if ($index <= 0) {
            return null;
        }

        $sorted = self::sortAndRenumberInstallmentPlan($rows);
        $previousDueDate = $sorted[$index - 1]['due_date'] ?? null;

        if (! $previousDueDate) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($previousDueDate);
    }

    /**
     * @param  array<int, array{label?: string, due_date?: ?string}>  $plan
     */
    public static function assertDueDatesInOrder(array $plan): void
    {
        $sorted = self::sortAndRenumberInstallmentPlan($plan);
        $previous = null;

        foreach ($sorted as $index => $row) {
            $dueDate = $row['due_date'] ?? null;

            if (! $dueDate) {
                continue;
            }

            $current = \Illuminate\Support\Carbon::parse($dueDate);

            if ($previous !== null && $current->lt($previous)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'installment_plan' => 'Installment due dates must be in chronological order.',
                ]);
            }

            $previous = $current;
        }
    }

    /**
     * @param  array<int, array{due_date?: ?string}>  $existingRows
     */
    public static function nextDueDate(array $existingRows): string
    {
        if ($existingRows === []) {
            return now()->toDateString();
        }

        $sorted = self::sortAndRenumberInstallmentPlan($existingRows);
        $lastRow = $sorted[array_key_last($sorted)];
        $lastDueDate = $lastRow['due_date'] ?? null;

        if ($lastDueDate) {
            return \Illuminate\Support\Carbon::parse($lastDueDate)->addMonth()->toDateString();
        }

        return now()->toDateString();
    }

    /**
     * @param  array<int, array{label?: string, amount?: mixed, due_date?: ?string}>  $existingRows
     * @return array{label: string, amount: string, due_date: string}
     */
    public static function newInstallmentRow(array $existingRows, float $targetTotal, int $nextIndex = 0): array
    {
        $remaining = self::remaining($targetTotal, $existingRows);

        return [
            'label' => self::installmentLabel($nextIndex + 1),
            'amount' => $remaining > 0 ? (string) round($remaining, 2) : '',
            'due_date' => self::nextDueDate($existingRows),
        ];
    }

    /**
     * @param  array<int, array{label?: string, amount?: mixed, due_date?: ?string}>  $rows
     * @return array<int, array{label?: string, amount?: mixed, due_date?: ?string}>
     */
    public static function fillBalanceOnLastRow(array $rows, float $targetTotal): array
    {
        if ($rows === []) {
            return $rows;
        }

        $lastKey = array_key_last($rows);
        $otherRows = $rows;
        unset($otherRows[$lastKey]);
        $otherTotal = self::sumAmounts($otherRows);
        $rows[$lastKey]['amount'] = (string) round(max(0, $targetTotal - $otherTotal), 2);

        return $rows;
    }

    /**
     * @return array<int, array{label: string, amount: string, due_date: ?string}>
     */
    public static function defaultTwoPartPlan(float $targetTotal): array
    {
        if ($targetTotal <= 0) {
            return [];
        }

        $half = round($targetTotal / 2, 2);
        $balance = round($targetTotal - $half, 2);

        return [
            [
                'label' => self::installmentLabel(1),
                'amount' => (string) $half,
                'due_date' => now()->toDateString(),
            ],
            [
                'label' => self::installmentLabel(2),
                'amount' => (string) $balance,
                'due_date' => now()->addMonth()->toDateString(),
            ],
        ];
    }

    /**
     * @return array{label: string, amount: string, due_date: string}
     */
    public static function singleFullFeeRow(float $targetTotal): array
    {
        return [
            'label' => self::installmentLabel(1),
            'amount' => (string) round($targetTotal, 2),
            'due_date' => now()->toDateString(),
        ];
    }
}
