<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Pages\HomeworkCheckPage;
use App\Services\HomeworkCheckReportService;
use App\Services\HomeworkCheckService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class HomeworkCheckReportWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -7;

    protected string $view = 'filament.widgets.homework-check-report';

    protected int | string | array $columnSpan = 'full';

    public string $reportDate = '';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null
            && FeatureGate::enabled(LicenseFeature::Homework)
            && CrmAccess::can($user, CrmPermission::HomeworkManage);
    }

    public function mount(): void
    {
        $this->reportDate = now()->subDay()->toDateString();
    }

    public function updatedReportDate(): void
    {
        $this->reportDate = app(HomeworkCheckReportService::class)->normalizeDate($this->reportDate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = app(HomeworkCheckReportService::class)->forDate($this->reportDate);
        $checks = app(HomeworkCheckService::class);
        $inWindow = $report['date'] >= $checks->earliestCheckDate()
            && $report['date'] <= $checks->latestCheckDate();

        $classes = [];

        foreach ($report['classes'] as $class) {
            $lines = [];

            foreach ($class['lines'] as $line) {
                $line['check_url'] = ($inWindow && $line['counts_open'])
                    ? HomeworkCheckPage::getUrl([
                        'batch_id' => $class['batch_id'],
                        'course_subject_id' => $line['assignment_id'],
                        'check_date' => $report['date'],
                    ])
                    : null;
                $lines[] = $line;
            }

            $class['lines'] = $lines;
            $classes[] = $class;
        }

        return [
            'dateLabel' => $report['date_label'],
            'isToday' => $report['is_today'],
            'maxDate' => now()->toDateString(),
            'classes' => $classes,
        ];
    }
}
