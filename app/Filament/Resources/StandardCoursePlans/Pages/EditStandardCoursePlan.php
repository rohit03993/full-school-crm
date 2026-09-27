<?php

namespace App\Filament\Resources\StandardCoursePlans\Pages;

use App\Enums\StandardCoursePlanStatus;
use App\Filament\Resources\StandardCoursePlans\StandardCoursePlanResource;
use App\Models\StandardCoursePlan;
use App\Services\StandardCoursePlanService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditStandardCoursePlan extends EditRecord
{
    protected static string $resource = StandardCoursePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markReady')
                ->label('Mark ready')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (StandardCoursePlan $record): bool => $record->status === StandardCoursePlanStatus::Draft)
                ->action(function (StandardCoursePlan $record): void {
                    app(StandardCoursePlanService::class)->markReady($record, Auth::user());
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Course plan is ready')
                        ->success()
                        ->send();
                }),
            Action::make('markDraft')
                ->label('Back to draft')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn (StandardCoursePlan $record): bool => $record->status === StandardCoursePlanStatus::Ready)
                ->action(function (StandardCoursePlan $record): void {
                    app(StandardCoursePlanService::class)->markDraft($record);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Course plan is a draft again')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(StandardCoursePlanService::class)->assertCanSave($data, $this->getRecord()->id);

        unset($data['status']);

        return $data;
    }

    protected function afterSave(): void
    {
        app(StandardCoursePlanService::class)->keepReadyOnlyWhenContentAllows($this->getRecord());
        $this->refreshFormData(['status']);
    }
}
