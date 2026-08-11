<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkAssignmentService;
use App\Support\CrmNotification;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreateHomeworkAssignment extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = HomeworkAssignmentResource::class;

    protected static ?string $title = 'Upload homework';

    protected static function crmHintKey(): ?string
    {
        return 'homework.create';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ((bool) ($data['send_whatsapp'] ?? false) && blank($data['whatsapp_template_name'] ?? null)) {
            throw ValidationException::withMessages([
                'data.whatsapp_template_name' => 'Select an approved 4-param WhatsApp template, or turn off Send WhatsApp.',
            ]);
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $attachment = $data['attachment'] ?? null;
        $filePath = null;

        if (is_array($attachment)) {
            $filePath = $attachment[array_key_first($attachment)] ?? reset($attachment) ?: null;
        } elseif (filled($attachment)) {
            $filePath = (string) $attachment;
        }

        $assignment = app(HomeworkAssignmentService::class)->create(Auth::user(), [
            'batch_id' => (int) $data['batch_id'],
            'title' => (string) $data['title'],
            'description' => (string) $data['description'],
            'file_path' => $filePath,
            'send_whatsapp' => (bool) ($data['send_whatsapp'] ?? false),
            'whatsapp_template_name' => $data['whatsapp_template_name'] ?? null,
        ]);

        $this->notifyWhatsAppOutcome($assignment, (bool) ($data['send_whatsapp'] ?? false));

        return $assignment;
    }

    protected function notifyWhatsAppOutcome(HomeworkAssignment $assignment, bool $requested): void
    {
        if (! $requested) {
            return;
        }

        $sent = (int) $assignment->whatsapp_sent_count;
        $failed = (int) $assignment->whatsapp_failed_count;

        if ($sent > 0 && $failed === 0) {
            Notification::make()
                ->title('WhatsApp sent')
                ->body($sent.' message(s) sent with the public homework link.')
                ->success()
                ->send();

            return;
        }

        if ($sent > 0) {
            CrmNotification::sendOutcome(
                'WhatsApp partially sent',
                $sent.' sent, '.$failed.' failed. Check WhatsApp → Message history.',
                false,
                warningOnFailure: true,
            );

            return;
        }

        Notification::make()
            ->title('WhatsApp was not sent')
            ->body(
                $failed > 0
                    ? $failed.' failed. Open WhatsApp → Message history for the error, confirm the template is APPROVED (4 params), then use Resend on this page.'
                    : 'No messages went out. Select an APPROVED 4-param template (homework_api / homework_update), Sync templates, then use Resend WhatsApp on this page.'
            )
            ->danger()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return HomeworkAssignmentResource::getUrl('view', ['record' => $this->record]);
    }
}
