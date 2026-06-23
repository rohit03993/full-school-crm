<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\Widget;

class RecentEnquiriesWidget extends Widget
{
    use VisibleToSuperAdminOnly;

    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.recent-enquiries';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $enquiries = app(CrmDashboardService::class)->recentEnquiries();

        return [
            'enquiries' => $enquiries,
            'allLeadsUrl' => EnquiryResource::getUrl('index'),
            'profileUrl' => fn (int $studentId): string => StudentProfilePage::getUrl(['record' => $studentId]),
        ];
    }
}
