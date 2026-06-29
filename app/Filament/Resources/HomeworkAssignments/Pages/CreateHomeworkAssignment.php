<?php

namespace App\Filament\Resources\HomeworkAssignments\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkAssignmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateHomeworkAssignment extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = HomeworkAssignmentResource::class;

    protected static ?string $title = 'Upload homework';

    protected static function crmHintKey(): ?string
    {
        return 'homework.create';
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

        if ($assignment->whatsapp_sent_count > 0 || $assignment->whatsapp_failed_count > 0) {
            Notification::make()
                ->title('WhatsApp notifications')
                ->body($assignment->whatsapp_sent_count.' sent, '.$assignment->whatsapp_failed_count.' failed.')
                ->success($assignment->whatsapp_failed_count === 0)
                ->warning($assignment->whatsapp_failed_count > 0)
                ->send();
        }

        return $assignment;
    }

    protected function getRedirectUrl(): string
    {
        return HomeworkAssignmentResource::getUrl('view', ['record' => $this->record]);
    }
}
