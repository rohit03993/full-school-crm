<?php

namespace App\Support;

use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Models\Course;
use App\Models\FeeStructure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class FeePlanSubmissionGuard
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertConvertable(array $data, ?Action $action = null): void
    {
        $courseId = $data['course_id'] ?? null;

        if ($courseId) {
            $course = Course::query()->find($courseId);

            if ($course && (float) $course->fee <= 0) {
                self::halt(
                    $action,
                    'Course fee not set',
                    'Update the fee for this course in Courses admin before converting.',
                    'course_id',
                );
            }
        }

        if (! ($data['use_installment_plan'] ?? false)) {
            return;
        }

        $net = \App\Filament\Forms\AdmissionFeePlanFormSchema::resolveNetFeeFromArray($data);
        self::assertFullyAllocated($net, $data['installment_plan'] ?? [], $action);
    }

    public static function canSubmitConvert(array $data): bool
    {
        $courseId = $data['course_id'] ?? null;

        if ($courseId) {
            $course = Course::query()->find($courseId);

            if ($course && (float) $course->fee <= 0) {
                return false;
            }
        }

        if (! ($data['use_installment_plan'] ?? false)) {
            return true;
        }

        $net = \App\Filament\Forms\AdmissionFeePlanFormSchema::resolveNetFeeFromArray($data);

        if ($net <= 0) {
            return true;
        }

        return FeePlanCalculator::isFullyAllocated($net, $data['installment_plan'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function canSubmitAdjustFees(array $data, FeeStructure $feeStructure): bool
    {
        $feeStructure->loadMissing('miscCharges');
        $miscTotal = $feeStructure->miscChargesTotal();
        $previousNet = round((float) ($feeStructure->net_fee ?? max(
            0,
            (float) $feeStructure->course_fee - (float) $feeStructure->discount_amount + $miscTotal,
        )), 2);
        $newNet = AdjustFeeStructureFormSchema::previewNetFromMounted($feeStructure, $data, $miscTotal);
        $paid = round((float) $feeStructure->paid_amount, 2);
        $newPending = round(max(0, $newNet - $paid), 2);

        if ($newPending > 0 && abs($newNet - $previousNet) > 0.01 && ! ($data['reschedule_installments'] ?? false)) {
            return false;
        }

        if (! ($data['reschedule_installments'] ?? false)) {
            return true;
        }

        $target = round(max(0, $newNet - $paid), 2);

        if ($target <= 0) {
            return true;
        }

        return FeePlanCalculator::isFullyAllocated($target, $data['installment_plan'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertAdjustFees(array $data, FeeStructure $feeStructure, ?Action $action = null): void
    {
        $feeStructure->loadMissing('miscCharges');
        $miscTotal = $feeStructure->miscChargesTotal();
        $previousNet = round((float) ($feeStructure->net_fee ?? max(
            0,
            (float) $feeStructure->course_fee - (float) $feeStructure->discount_amount + $miscTotal,
        )), 2);
        $newNet = AdjustFeeStructureFormSchema::previewNetFromMounted($feeStructure, $data, $miscTotal);
        $paid = round((float) $feeStructure->paid_amount, 2);
        $newPending = round(max(0, $newNet - $paid), 2);

        if ($newPending > 0 && abs($newNet - $previousNet) > 0.01 && ! ($data['reschedule_installments'] ?? false)) {
            self::halt(
                $action,
                'Reschedule installments required',
                'Turn on “Reschedule remaining installments” when the balance due changes so installment rows match the new amount.',
                'reschedule_installments',
            );
        }

        if (! ($data['reschedule_installments'] ?? false)) {
            return;
        }

        $target = round(max(0, $newNet - $paid), 2);

        if ($target <= 0) {
            return;
        }

        self::assertFullyAllocated($target, $data['installment_plan'] ?? [], $action);
    }

    /**
     * @param  array<int, array<string, mixed>>  $plan
     */
    public static function assertFullyAllocated(float $target, array $plan, ?Action $action = null): void
    {
        if ($target <= 0) {
            return;
        }

        if (FeePlanCalculator::isFullyAllocated($target, $plan)) {
            return;
        }

        $remaining = FeePlanCalculator::remaining($target, $plan);
        $message = $remaining > 0
            ? 'Installment amounts are short by ₹'.number_format($remaining, 2).'.'
            : 'Installment amounts exceed the balance by ₹'.number_format(abs($remaining), 2).'.';

        self::halt($action, 'Installments do not balance', $message, 'installment_plan');
    }

    private static function halt(?Action $action, string $title, string $body, string $field): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();

        if ($action) {
            $action->halt();

            return;
        }

        throw ValidationException::withMessages([
            $field => $body,
        ]);
    }
}
