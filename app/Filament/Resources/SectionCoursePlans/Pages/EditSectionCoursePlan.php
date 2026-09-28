<?php

namespace App\Filament\Resources\SectionCoursePlans\Pages;

use App\Enums\CrmPermission;
use App\Enums\SectionCoursePlanStatus;
use App\Filament\Resources\SectionCoursePlans\SectionCoursePlanResource;
use App\Models\SectionCoursePlan;
use App\Services\SectionCoursePlanService;
use App\Support\CrmAccess;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class EditSectionCoursePlan extends EditRecord
{
    protected static string $resource = SectionCoursePlanResource::class;

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled(fn (): bool => ! app(SectionCoursePlanService::class)->canEditContent($this->sectionPlan(), Auth::user()));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submitPlan')
                ->label('Submit')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (): bool => app(SectionCoursePlanService::class)->canEditContent($this->sectionPlan(), Auth::user()))
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    app(SectionCoursePlanService::class)->submit($this->sectionPlan()->fresh(), Auth::user());
                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Plan submitted')
                        ->body('The academic head can now finalize it or send it back.')
                        ->success()
                        ->send();
                }),
            Action::make('finalizePlan')
                ->label('Finalize')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Students will see these topics. The teacher who submitted cannot finalize this plan.')
                ->visible(fn (): bool => $this->canReview())
                ->action(function (): void {
                    app(SectionCoursePlanService::class)->finalize($this->sectionPlan(), Auth::user());
                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Plan is final')
                        ->body('Version '.$this->sectionPlan()->version.' is the plan students see.')
                        ->success()
                        ->send();
                }),
            Action::make('sendBackPlan')
                ->label('Send back')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->canReview())
                ->form([
                    Textarea::make('comment')
                        ->label('What should the teacher change?')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(SectionCoursePlanService::class)->sendBack(
                        $this->sectionPlan(),
                        Auth::user(),
                        (string) ($data['comment'] ?? ''),
                    );
                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Sent back to the teacher')
                        ->success()
                        ->send();
                }),
            Action::make('openChange')
                ->label('Open a change')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => $this->sectionPlan()->status === SectionCoursePlanStatus::Final
                    && CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage))
                ->form([
                    Textarea::make('reason')
                        ->label('Why does this plan need a change?')
                        ->required()
                        ->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(SectionCoursePlanService::class)->openChange(
                        $this->sectionPlan(),
                        Auth::user(),
                        (string) ($data['reason'] ?? ''),
                    );
                    $this->record->refresh();
                    $this->fillForm();

                    Notification::make()
                        ->title('Change opened')
                        ->body('The subject teacher can edit and submit again. Students still see the last final version.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (): bool => SectionCoursePlanResource::canDelete($this->sectionPlan())),
        ];
    }

    protected function getFormActions(): array
    {
        if (! app(SectionCoursePlanService::class)->canEditContent($this->sectionPlan(), Auth::user())) {
            return [];
        }

        return parent::getFormActions();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $plan = $this->sectionPlan();
        $note = filled($plan->review_comment) ? ' Note: '.$plan->review_comment : '';

        return match ($plan->status) {
            SectionCoursePlanStatus::Draft => ((int) $plan->version > 0
                ? 'Version '.$plan->version.' is still what students see. Edit this change, click Save, then Submit.'
                : 'Change the days, the book, or the counts. Click Save, then Submit.')
                .$note,
            SectionCoursePlanStatus::Submitted => 'Waiting for the academic head to finalize or send this back.',
            SectionCoursePlanStatus::SentBack => 'The academic head sent this back. Fix it, click Save, then Submit.'.$note,
            SectionCoursePlanStatus::Final => 'This is final version '.$plan->version.'. The teacher sees the days. Students see the topics and the counts only.',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! app(SectionCoursePlanService::class)->canEditContent($this->sectionPlan(), Auth::user())) {
            throw ValidationException::withMessages([
                'chapters' => 'Only the subject teacher can change this plan, and only before it is final.',
            ]);
        }

        unset($data['status'], $data['version'], $data['review_comment'], $data['change_reason']);

        return $data;
    }

    protected function sectionPlan(): SectionCoursePlan
    {
        /** @var SectionCoursePlan $plan */
        $plan = $this->getRecord();

        return $plan;
    }

    protected function canReview(): bool
    {
        $plan = $this->sectionPlan();
        $user = Auth::user();

        return $plan->status === SectionCoursePlanStatus::Submitted
            && $user !== null
            && CrmAccess::can($user, CrmPermission::AcademicsManage)
            && (int) $plan->submitted_by_user_id !== (int) $user->id;
    }
}
