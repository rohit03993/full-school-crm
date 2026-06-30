<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * @deprecated Use {@see AttendancePage} with manual mode.
 */
class BatchAttendancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'batch-attendance';

    public function mount(): void
    {
        $params = ['mode' => 'manual'];

        if ($batchId = request()->integer('batch_id')) {
            $params['batch_id'] = $batchId;
        }

        $this->redirect(AttendancePage::getUrl($params), navigate: true);
    }
}
