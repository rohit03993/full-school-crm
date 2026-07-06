<?php

namespace App\Filament\Resources\WhatsAppLiveCampaigns\Pages;

use App\Enums\WhatsAppLiveCampaignStatus;
use App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource;
use App\Services\WhatsAppLiveCampaignService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditWhatsAppLiveCampaign extends EditRecord
{
    protected static string $resource = WhatsAppLiveCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('goLive')
                ->label('Go live')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === WhatsAppLiveCampaignStatus::Draft)
                ->action(function (WhatsAppLiveCampaignService $service): void {
                    try {
                        $service->goLive($this->record);
                        $this->record->refresh();

                        Notification::make()
                            ->title('Campaign is live')
                            ->body('External systems can trigger this campaign by name.')
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('Could not go live')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Action::make('pause')
                ->label('Pause')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === WhatsAppLiveCampaignStatus::Live)
                ->action(function (WhatsAppLiveCampaignService $service): void {
                    $service->pause($this->record);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Campaign paused')
                        ->body('API triggers and automations will skip this campaign until you go live again.')
                        ->warning()
                        ->send();
                }),
        ];
    }
}
