<?php

namespace App\Filament\Resources\SectionCoursePlans\Pages;

use App\Enums\CrmPermission;
use App\Enums\StandardCoursePlanStatus;
use App\Filament\Resources\SectionCoursePlans\SectionCoursePlanResource;
use App\Models\Batch;
use App\Models\SectionCoursePlan;
use App\Models\StandardCoursePlan;
use App\Services\SectionCoursePlanService;
use App\Support\CrmAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ListSectionCoursePlans extends ListRecords
{
    protected static string $resource = SectionCoursePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copyOntoSection')
                ->label('Copy onto a section')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage))
                ->form([
                    Select::make('standard_course_plan_id')
                        ->label('Ready course plan')
                        ->options(fn (): array => StandardCoursePlan::query()
                            ->where('status', StandardCoursePlanStatus::Ready)
                            ->with(['course', 'courseSubject', 'academicSession'])
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn (StandardCoursePlan $plan): array => [
                                $plan->id => trim(implode(' · ', array_filter([
                                    $plan->academicSession?->name,
                                    $plan->course?->name,
                                    $plan->courseSubject?->name,
                                ]))),
                            ])
                            ->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    Select::make('batch_id')
                        ->label('Section')
                        ->options(function (Get $get): array {
                            $plan = StandardCoursePlan::query()->find($get('standard_course_plan_id'));

                            if (! $plan) {
                                return [];
                            }

                            return Batch::query()
                                ->where('course_id', $plan->course_id)
                                ->where('academic_session_id', $plan->academic_session_id)
                                ->whereHas('subjects', fn ($query) => $query->whereKey($plan->course_subject_id))
                                ->with(['course', 'academicSession'])
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Batch $batch): array => [$batch->id => $batch->selectLabel()])
                                ->all();
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $plan = StandardCoursePlan::query()->find($data['standard_course_plan_id'] ?? null);
                    $batch = Batch::query()->find($data['batch_id'] ?? null);
                    $user = Auth::user();

                    if (! $plan || ! $batch || ! $user) {
                        return;
                    }

                    try {
                        $sectionPlan = app(SectionCoursePlanService::class)->copyFromStandard($plan, $batch, $user);
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('Could not copy')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Section plan copied')
                        ->body($sectionPlan->courseSubject?->name.' is ready for '.$sectionPlan->batch?->selectLabel().'.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Copy a ready course plan onto one section. The subject teacher edits and submits. The academic head finalizes it.';
    }
}
