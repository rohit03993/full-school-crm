<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BatchAttendancePage;
use App\Filament\Pages\ReportsPage;
use App\Filament\Pages\StudentSearchPage;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Services\CrmDashboardService;
use App\Support\InstituteSettings;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardHeroWidget extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.dashboard-hero';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $stats = app(CrmDashboardService::class)->stats();
        $branding = InstituteSettings::forDocuments();

        return [
            'userName' => Auth::user()?->name ?? 'there',
            'instituteName' => $branding['name'],
            'tagline' => $branding['tagline'],
            'todayLabel' => now()->format('l, j F Y'),
            'todayEnquiries' => $stats['today_enquiries'],
            'feeToday' => $stats['fee_collection_today'],
            'pendingAdmissions' => $stats['pending_admissions'],
            'quickActions' => [
                [
                    'label' => 'Search Student',
                    'description' => 'Find profile or add lead',
                    'icon' => 'heroicon-o-magnifying-glass',
                    'url' => StudentSearchPage::getUrl(),
                ],
                [
                    'label' => 'All Leads',
                    'description' => 'Browse enquiry pipeline',
                    'icon' => 'heroicon-o-inbox-stack',
                    'url' => EnquiryResource::getUrl('index'),
                ],
                [
                    'label' => 'Attendance',
                    'description' => 'Mark batch attendance',
                    'icon' => 'heroicon-o-calendar-days',
                    'url' => BatchAttendancePage::getUrl(),
                ],
                [
                    'label' => 'Reports',
                    'description' => 'Export CSV & PDF',
                    'icon' => 'heroicon-o-document-chart-bar',
                    'url' => ReportsPage::getUrl(),
                ],
            ],
        ];
    }
}
