<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Admissions\AdmissionResource;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\Widget;

class PendingAdmissionsWidget extends Widget
{
    use VisibleToSuperAdminOnly;

    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.pending-admissions';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'admissions' => app(CrmDashboardService::class)->pendingAdmissions(),
            'allAdmissionsUrl' => AdmissionResource::getUrl('index'),
            'viewUrl' => fn (int $admissionId): string => AdmissionResource::getUrl('view', ['record' => $admissionId]),
        ];
    }
}
