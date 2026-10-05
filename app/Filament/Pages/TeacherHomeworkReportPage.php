<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\HomeworkSubmissionService;
use App\Services\TeacherHomeworkScoreService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TeacherHomeworkReportPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Teacher scores';

    protected static ?string $title = 'Teacher scores';

    protected static ?int $navigationSort = 48;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $teacherId = null;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return self::canSeeAllTeachers()
            || app(HomeworkSubmissionService::class)->canSubmit($user);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canSeeAllTeachers();
    }

    public static function canSeeAllTeachers(): bool
    {
        $user = Auth::user();

        return $user !== null
            && FeatureGate::enabled(LicenseFeature::Homework)
            && CrmAccess::can($user, CrmPermission::HomeworkManage);
    }

    public function getSubheading(): ?string
    {
        if ($this->canSeeAllTeachers() && $this->teacherId === null) {
            return 'Lowest score first. Every day in the range counts, including Sunday.';
        }

        return 'Homework given and homework checked for this teacher.';
    }

    public function mount(): void
    {
        [$this->dateFrom, $this->dateTo] = app(TeacherHomeworkScoreService::class)->defaultRange();
        $requested = request()->integer('teacher');

        if ($requested > 0 && self::canSeeAllTeachers()) {
            $this->teacherId = $requested;
        }
    }

    public function updatedDateFrom(): void
    {
        $this->applyRange();
    }

    public function updatedDateTo(): void
    {
        $this->applyRange();
    }

    public function openTeacher(int $teacherId): void
    {
        if (! self::canSeeAllTeachers() || $teacherId < 1) {
            return;
        }

        $this->teacherId = $teacherId;
    }

    public function clearTeacher(): void
    {
        $this->teacherId = null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.teacher-homework-scores')
                ->viewData(function (): array {
                    $onlyUserId = self::canSeeAllTeachers() ? $this->teacherId : Auth::id();
                    $report = app(TeacherHomeworkScoreService::class)->report(
                        $this->dateFrom,
                        $this->dateTo,
                        $onlyUserId ? (int) $onlyUserId : null,
                        $onlyUserId !== null,
                    );

                    return [
                        'report' => $report,
                        'canSeeAll' => self::canSeeAllTeachers(),
                        'showingOne' => $onlyUserId !== null,
                        'maxDate' => now()->toDateString(),
                    ];
                }),
        ]);
    }

    protected function applyRange(): void
    {
        [$this->dateFrom, $this->dateTo] = app(TeacherHomeworkScoreService::class)
            ->normalizeRange($this->dateFrom, $this->dateTo);
    }
}
