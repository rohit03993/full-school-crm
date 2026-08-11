<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Services\HomeworkWhatsAppService;
use App\Support\CrmAccess;
use App\Enums\CrmPermission;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewHomeworkAssignment extends ViewRecord
{
    protected static string $resource = HomeworkAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resendWhatsApp')
                ->label('Resend WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->visible(fn (): bool => CrmAccess::can(Auth::user(), CrmPermission::HomeworkManage))
                ->form([
                    Select::make('whatsapp_template_name')
                        ->label('WhatsApp template')
                        ->options(fn (): array => app(HomeworkWhatsAppService::class)->shareTemplateOptions())
                        ->default(fn (): ?string => app(HomeworkWhatsAppService::class)->defaultShareTemplateName())
                        ->required()
                        ->searchable()
                        ->helperText('Approved Meta template with 4 params: name, roll, title, public link.'),
                ])
                ->action(function (array $data): void {
                    /** @var \App\Models\HomeworkAssignment $record */
                    $record = $this->getRecord();

                    $result = app(HomeworkWhatsAppService::class)->notifyBatch(
                        $record,
                        filled($data['whatsapp_template_name'] ?? null) ? (string) $data['whatsapp_template_name'] : null,
                    );

                    $record->update([
                        'whatsapp_sent_count' => $result['sent'],
                        'whatsapp_failed_count' => $result['failed'],
                    ]);

                    $this->refreshFormData(['whatsapp_sent_count', 'whatsapp_failed_count']);

                    if ($result['sent'] > 0 && $result['failed'] === 0) {
                        Notification::make()
                            ->title('WhatsApp resent')
                            ->body($result['sent'].' message(s) sent via '.$result['template'].'.')
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($result['sent'] > 0 ? 'WhatsApp partially sent' : 'WhatsApp not sent')
                        ->body(
                            ($result['error'] ?? 'No messages sent.')
                            .' (sent '.$result['sent'].', failed '.$result['failed'].')'
                        )
                        ->danger()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
