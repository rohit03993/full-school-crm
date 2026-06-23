<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;

trait FinishesAttendanceSave
{
    protected function finishAttendanceSave(string $title, string $body, ?string $redirectTo = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->duration(8000)
            ->send();

        $this->redirect($redirectTo ?? static::getUrl(), navigate: true);
    }

    /**
     * @param  array<int|string, mixed>  $marks
     * @return array<int, bool>
     */
    protected function normalizeBooleanMarksFromClient(array $marks): array
    {
        $normalized = [];

        foreach ($marks as $studentId => $value) {
            $normalized[(int) $studentId] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $marks
     * @return array<int, string>
     */
    protected function normalizeStatusMarksFromClient(array $marks): array
    {
        $normalized = [];

        foreach ($marks as $studentId => $value) {
            $normalized[(int) $studentId] = (string) $value;
        }

        return $normalized;
    }
}
