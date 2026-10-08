<?php

namespace App\Livewire;

use App\Filament\Concerns\InteractsWithStudentWhatsAppInbox;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Services\StudentWhatsAppThreadService;
use App\Services\WhatsAppInboxContactResolver;
use App\Support\WhatsAppInboxContact;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class WhatsAppInboxThread extends Component
{
    use InteractsWithStudentWhatsAppInbox;
    use WithFileUploads;

    public ?int $selectedStudentId = null;

    public ?string $selectedPhone = null;

    public ?string $threadChangeStamp = null;

    public bool $composerLockNotified = false;

    public function mount(?string $phone = null, ?int $studentId = null): void
    {
        $this->guardInboxAccess();
        $this->initializeWhatsAppInboxState();

        if (filled($phone) || filled($studentId)) {
            $this->openChat((string) $phone, $studentId, true);
        }
    }

    public function boot(): void
    {
        $this->guardInboxAccess();
    }

    #[On('open-whatsapp-chat')]
    public function openChatFromList(string $phone = '', mixed $studentId = null): void
    {
        $this->openChat($phone, $studentId, false);
    }

    public function openChat(string $phone = '', mixed $studentId = null, bool $silent = false): void
    {
        $this->guardInboxAccess();

        try {
            $thread = app(StudentWhatsAppThreadService::class);
            $studentId = is_numeric($studentId) && (int) $studentId > 0 ? (int) $studentId : null;
            $normalized = $thread->normalizePhoneForStorage($phone);

            if ($normalized === '' && $studentId) {
                $student = Student::query()->find($studentId);
                $normalized = $student
                    ? $thread->normalizePhoneForStorage((string) $student->mobile)
                    : '';
            }

            $this->selectedPhone = $normalized !== '' ? $normalized : null;
            $student = $studentId
                ? Student::query()->find($studentId)
                : ($this->selectedPhone ? $thread->findStudentByPhone($this->selectedPhone) : null);
            $this->selectedStudentId = $student?->id;

            $this->resetMessagesTab();
            $this->loadMessagesTab();
            $this->threadChangeStamp = $this->threadStamp();

            if (! $silent) {
                $this->dispatch('whatsapp-chat-opened', phone: $this->selectedPhone, studentId: $this->selectedStudentId);
            }
        } catch (\Throwable $exception) {
            report($exception);
            $this->selectedPhone = null;
            $this->selectedStudentId = null;
            $this->resetMessagesTab();

            Notification::make()
                ->title('Could not open chat')
                ->body('Please refresh the page. If this continues, run migrations on the server.')
                ->danger()
                ->send();
        }
    }

    public function clearConversation(): void
    {
        $this->selectedPhone = null;
        $this->selectedStudentId = null;
        $this->threadChangeStamp = null;
        $this->resetMessagesTab();
        $this->dispatch('whatsapp-chat-closed');
    }

    public function pollThread(): void
    {
        if ($this->inboxPollShouldSkip() || (blank($this->selectedPhone) && blank($this->selectedStudentId))) {
            $this->skipRender();

            return;
        }

        $stamp = $this->threadStamp();

        if ($stamp === $this->threadChangeStamp) {
            $this->skipRender();

            return;
        }

        $this->messagesTabLoaded = false;
        $this->loadMessagesTab();
        $this->threadChangeStamp = $stamp;
    }

    protected function guardInboxAccess(): void
    {
        abort_unless(WhatsAppInboxPage::canAccess(), 403);
    }

    protected function threadStamp(): string
    {
        if (! Schema::hasTable('meta_whatsapp_messages')) {
            return '';
        }

        $phone = $this->selectedPhone;
        $studentId = $this->selectedStudentId;

        if (blank($phone) && blank($studentId)) {
            return '';
        }

        $row = MetaWhatsAppMessage::query()
            ->where(function ($query) use ($phone, $studentId): void {
                if (filled($phone)) {
                    $query->where('phone', $phone);
                }

                if ($studentId) {
                    filled($phone)
                        ? $query->orWhere('student_id', $studentId)
                        : $query->where('student_id', $studentId);
                }
            })
            ->selectRaw('MAX(id) as max_id, MAX(updated_at) as max_updated')
            ->first();

        return ($row?->max_id ?? '').'|'.($row?->max_updated ?? '');
    }

    protected function whatsAppMessageStudent(): ?Student
    {
        if ($this->selectedStudentId) {
            return Student::query()->find($this->selectedStudentId);
        }

        if (filled($this->selectedPhone)) {
            return app(StudentWhatsAppThreadService::class)->findStudentByPhone($this->selectedPhone);
        }

        return null;
    }

    protected function whatsAppInboxPhone(): ?string
    {
        return $this->selectedPhone;
    }

    protected function whatsAppInboxCompactLayout(): bool
    {
        return true;
    }

    protected function afterWhatsAppMessageSent(): void
    {
        $this->threadChangeStamp = $this->threadStamp();
        $this->dispatch('whatsapp-inbox-refresh-list');
    }

    protected function afterWhatsAppComposerChanged(): void
    {
        $locked = trim($this->metaReplyText) !== ''
            || $this->metaReplyAttachment !== null
            || $this->showMetaReplyAttachment
            || filled($this->sendWhatsAppTemplateId);

        if ($this->composerLockNotified === $locked) {
            return;
        }

        $this->composerLockNotified = $locked;
        $this->dispatch('whatsapp-composer-lock', locked: $locked);
    }

    protected function inboxPollShouldSkip(): bool
    {
        if (trim($this->metaReplyText) !== '') {
            return true;
        }

        if ($this->metaReplyAttachment !== null || $this->showMetaReplyAttachment) {
            return true;
        }

        return (bool) $this->sendWhatsAppTemplateId;
    }

    public function render(): View
    {
        $phone = $this->selectedPhone;
        $chatOpen = filled($phone) || filled($this->selectedStudentId);

        return view('livewire.whatsapp-inbox-thread', [
            'chatOpen' => $chatOpen,
            'chatStudent' => $chatOpen ? $this->whatsAppMessageStudent() : null,
            'chatContact' => filled($phone)
                ? app(WhatsAppInboxContactResolver::class)->resolve((string) $phone)
                : WhatsAppInboxContact::unknown(),
            'selectedPhone' => $phone,
            'metaRoutingActive' => $this->metaRoutingActive,
            'metaSessionOpen' => $this->metaSessionOpen,
            'messagesViewData' => ($chatOpen && $this->messagesTabLoaded)
                ? $this->whatsAppMessagesViewData()
                : null,
        ]);
    }
}
