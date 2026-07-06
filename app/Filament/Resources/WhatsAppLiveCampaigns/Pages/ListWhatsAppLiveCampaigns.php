<?php

namespace App\Filament\Resources\WhatsAppLiveCampaigns\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource;
use App\Services\WhatsAppIntegrationApiService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListWhatsAppLiveCampaigns extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = WhatsAppLiveCampaignResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.live_campaigns';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateApiKey')
                ->label('Generate API key')
                ->icon('heroicon-o-key')
                ->requiresConfirmation()
                ->modalHeading('Generate integration API key')
                ->modalDescription('This replaces any existing key. Copy it now — it cannot be shown again in full.')
                ->action(function (WhatsAppIntegrationApiService $api): void {
                    $key = $api->generateKey();

                    Notification::make()
                        ->title('API key generated')
                        ->body(new HtmlString(
                            'Copy this key now:<br><code class="mt-2 block break-all rounded bg-gray-100 p-2 font-mono text-xs dark:bg-gray-800">'
                            .e($key).'</code>'
                        ))
                        ->success()
                        ->persistent()
                        ->send();
                }),
            CreateAction::make()->label('New live campaign'),
        ];
    }
}
