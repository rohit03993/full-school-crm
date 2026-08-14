<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Services\HomeworkSubmissionService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class HomeworkPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $title = 'Homework';

    protected static ?int $navigationSort = 44;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::homework();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::HomeworkManage)
            || app(HomeworkSubmissionService::class)->canSubmit($user);
    }

    public function getSubheading(): ?string
    {
        return 'One place for the complete daily homework workflow.';
    }

    public function content(Schema $schema): Schema
    {
        $user = Auth::user();
        $canManage = CrmAccess::can($user, CrmPermission::HomeworkManage);

        return $schema->components([
            View::make('filament.pages.partials.homework-hub')
                ->viewData([
                    'canManage' => $canManage,
                    'canSubmit' => SubmitHomeworkPage::canAccess(),
                    'canReview' => HomeworkReviewPage::canAccess(),
                    'canCheck' => HomeworkCheckPage::canAccess(),
                    'canViewHistory' => HomeworkAssignmentResource::canAccess(),
                    'submitUrl' => SubmitHomeworkPage::getUrl(),
                    'reviewUrl' => HomeworkReviewPage::getUrl(),
                    'checkUrl' => HomeworkCheckPage::getUrl(),
                    'historyUrl' => HomeworkAssignmentResource::getUrl('index'),
                ]),
        ]);
    }
}
