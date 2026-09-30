<?php

namespace App\Filament\Resources\MetaWhatsAppTemplates\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Services\HomeworkNotDoneTemplateUpdateService;
use App\Services\MetaWhatsAppTemplateSyncService;
use App\Support\CrmNotification;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetaWhatsAppTemplates extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = MetaWhatsAppTemplateResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.templates';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('updateHomeworkNotDone')
                ->label('Update homework not done wording')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->requiresConfirmation()
                ->modalHeading('Update homework not done wording')
                ->modalDescription('This sends the spaced sentence to WhatsApp under the same name homework_not_done. WhatsApp must approve it again. Not Done messages pause until the status is APPROVED.')
                ->action(function (HomeworkNotDoneTemplateUpdateService $updates): void {
                    $result = $updates->sendUpdatedWording();

                    CrmNotification::sendOutcome(
                        $result['status'] === 'success' ? 'Wording sent to WhatsApp' : 'Could not update the wording',
                        $result['message'],
                        $result['status'] === 'success',
                    );
                }),
            Action::make('syncFromMeta')
                ->label('Sync from Meta')
                ->icon('heroicon-o-arrow-path')
                ->action(function (MetaWhatsAppTemplateSyncService $sync): void {
                    $result = $sync->sync();

                    CrmNotification::sendOutcome(
                        $result['synced'].' template(s) synced',
                        $result['message'],
                        $result['status'] === 'success' || $result['status'] === 'warning',
                    );
                }),
            CreateAction::make()->label('New template'),
        ];
    }
}
