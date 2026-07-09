<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\ExamWindowStatus;
use App\Enums\LicenseFeature;
use App\Filament\Pages\TestMarksReviewPage;
use App\Models\ExamWindow;
use App\Services\ExamWindowService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\FeatureGate;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ExamWindowPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Exam window';

    protected static ?string $slug = 'exam-window';

    public ?int $windowId = null;

    public ?ExamWindow $window = null;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return false;
        }

        return CrmAccess::hasPanelAccess(Auth::user());
    }

    public function mount(): void
    {
        $id = request()->query('window');

        if (! filled($id)) {
            $this->redirect(ExamWindowsPage::getUrl());

            return;
        }

        $this->windowId = (int) $id;
        $this->window = ExamWindow::query()
            ->with([
                'batch.course',
                'batch.academicSession',
                'activityType',
                'subjects.courseSubject',
                'subjects.enteredBy',
                'subjects.activitySession',
                'submittedBy',
                'approvedBy',
            ])
            ->findOrFail($this->windowId);
    }

    public function getTitle(): string
    {
        return $this->window?->test_name ?? static::$title;
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('exam_windows.detail');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All exam windows')
                ->url(ExamWindowsPage::getUrl())
                ->color('gray'),
        ];
    }

    public function openForTeachers(ExamWindowService $service): void
    {
        abort_unless($this->window, 404);

        try {
            $service->open($this->window, Auth::user());
            $this->refreshWindow();
            Notification::make()->title('Opened for teachers')->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not open')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function submitForApproval(ExamWindowService $service): void
    {
        abort_unless($this->window, 404);

        try {
            $service->submit($this->window, Auth::user());
            $this->refreshWindow();
            Notification::make()->title('Submitted for admin approval')->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not submit')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function approve(ExamWindowService $service): void
    {
        abort_unless($this->window, 404);

        try {
            $service->approve($this->window, Auth::user());
            $this->refreshWindow();
            Notification::make()->title('Exam approved — you can publish results')->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not approve')
                ->body(collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        $service = app(ExamWindowService::class);
        $user = Auth::user();

        return $schema->components([
            View::make('filament.pages.partials.exam-window-detail')
                ->viewData(fn (): array => [
                    'window' => $this->window,
                    'progress' => $this->window ? $service->progress($this->window) : null,
                    'batchLabel' => $this->window?->batch
                        ? ClassSectionLabel::forBatch($this->window->batch, includeSession: true, includeShift: false)
                        : '—',
                    'marksEntryUrl' => fn (?int $sessionId): string => $sessionId
                        ? ActivityAttendancePage::getUrl(['id' => $sessionId])
                        : '#',
                    'reviewUrl' => $this->window
                        ? TestMarksReviewPage::getUrl(['group' => $this->window->test_key])
                        : '#',
                    'canOpen' => $this->window?->status === ExamWindowStatus::Draft
                        && $service->canUserApprove($user ?? new \App\Models\User),
                    'canSubmit' => $this->window && $user
                        ? $service->canUserSubmit($user, $this->window)
                        : false,
                    'canApprove' => $this->window?->status === ExamWindowStatus::Submitted
                        && $user
                        && $service->canUserApprove($user),
                    'canEnterMarks' => fn (\App\Models\ExamWindowSubject $row): bool => $this->window && $user
                        ? $service->canUserEnterSubject($user, $this->window, $row)
                        : false,
                    'canPublish' => $this->window?->status === ExamWindowStatus::Approved
                        && CrmAccess::can($user, CrmPermission::MarksImport),
                ]),
        ]);
    }

    protected function refreshWindow(): void
    {
        if (! $this->windowId) {
            return;
        }

        $this->window = ExamWindow::query()
            ->with([
                'batch.course',
                'batch.academicSession',
                'activityType',
                'subjects.courseSubject',
                'subjects.enteredBy',
                'subjects.activitySession',
                'submittedBy',
                'approvedBy',
            ])
            ->findOrFail($this->windowId);
    }
}
