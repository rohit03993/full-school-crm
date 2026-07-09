<?php

namespace App\Filament\Pages;

use App\Services\BatchStaffAssignmentService;
use App\Services\ExamWindowService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class MyTeachingAssignmentsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'My classes';

    protected static ?string $title = 'My classes';

    protected static ?int $navigationSort = -198;

    public function getSubheading(): ?string
    {
        return CrmHint::text('teaching.assignments');
    }

    public static function canAccess(): bool
    {
        return CrmAccess::hasPanelAccess(Auth::user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        $user = Auth::user();
        $examService = app(ExamWindowService::class);

        return $schema->components([
            View::make('filament.pages.partials.my-teaching-assignments')
                ->viewData(fn (): array => [
                    'assignments' => $user
                        ? app(BatchStaffAssignmentService::class)->assignmentsForUser($user)
                        : [],
                    'pendingMarkEntries' => $user
                        ? $examService->pendingEntriesForUser($user)
                        : [],
                    'submitCandidates' => $user
                        ? $examService->submitCandidatesForUser($user)
                        : [],
                    'marksEntryUrl' => fn (?int $sessionId): string => $sessionId
                        ? ActivityAttendancePage::getUrl(['id' => $sessionId])
                        : '#',
                    'examWindowUrl' => fn (int $windowId): string => ExamWindowPage::getUrl(['window' => $windowId]),
                ]),
        ]);
    }
}
