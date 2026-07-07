<?php

namespace App\Filament\Concerns;

use App\Enums\LicenseFeature;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\Student;
use App\Services\MetaWhatsAppInboxService;
use App\Services\StudentWhatsAppTemplateComposer;
use App\Services\StudentWhatsAppThreadService;
use App\Services\WhatsAppCampaignService;
use App\Services\WhatsAppProviderResolver;
use App\Services\WhatsAppTemplateCatalog;
use App\Support\FeatureGate;
use App\Support\StudentWhatsAppThreadItem;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait InteractsWithStudentWhatsAppInbox
{
    public bool $messagesTabLoaded = false;

    /** @var list<array<string, mixed>> */
    public array $messageThread = [];

    public string $metaReplyText = '';

    public ?TemporaryUploadedFile $metaReplyAttachment = null;

    public bool $showMetaReplyAttachment = false;

    public bool $metaSessionOpen = false;

    public bool $metaRoutingActive = false;

    public string $whatsappProviderLabel = '';

    public ?int $sendWhatsAppTemplateId = null;

    /** @var list<string|null> */
    public array $sendWhatsAppTemplateParams = [];

    /** @var list<array<string, mixed>> */
    public array $sendWhatsAppTemplateFields = [];

    public int $sendWhatsAppTemplateParamCount = 0;

    public ?string $sendWhatsAppTemplatePreview = null;

    public ?string $sendWhatsAppSelectedTemplateName = null;

    abstract protected function whatsAppMessageStudent(): ?Student;

    protected function initializeWhatsAppInboxState(): void
    {
        $this->messageThread = [];
    }

    public function updatedSendWhatsAppTemplateId(): void
    {
        $this->refreshWhatsAppTemplateComposer();
    }

    public function updatedSendWhatsAppTemplateParams($value, $key = null): void
    {
        $this->refreshWhatsAppTemplatePreview();
    }

    protected function refreshWhatsAppTemplateComposer(): void
    {
        $student = $this->whatsAppMessageStudent();

        $this->sendWhatsAppTemplateFields = [];
        $this->sendWhatsAppTemplateParams = [];
        $this->sendWhatsAppTemplateParamCount = 0;
        $this->sendWhatsAppTemplatePreview = null;
        $this->sendWhatsAppSelectedTemplateName = null;

        if (! $this->sendWhatsAppTemplateId || ! $student) {
            return;
        }

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            return;
        }

        $compose = app(StudentWhatsAppTemplateComposer::class)->compose(
            $template,
            $student,
            Auth::user(),
        );

        $this->sendWhatsAppTemplateFields = $compose['fields'];
        $this->sendWhatsAppTemplateParams = $compose['defaults'];
        $this->sendWhatsAppTemplateParamCount = $compose['param_count'];
        $this->sendWhatsAppTemplatePreview = $compose['preview_body'];
        $this->sendWhatsAppSelectedTemplateName = $compose['template_name'];
    }

    protected function refreshWhatsAppTemplatePreview(): void
    {
        if (! $this->sendWhatsAppTemplateId) {
            $this->sendWhatsAppTemplatePreview = null;

            return;
        }

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            return;
        }

        $this->sendWhatsAppTemplatePreview = app(StudentWhatsAppTemplateComposer::class)->preview(
            $template,
            $this->sendWhatsAppTemplateParams,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function whatsAppMessagesViewData(): array
    {
        try {
            $student = $this->whatsAppMessageStudent();
            $catalog = app(WhatsAppTemplateCatalog::class);

            return [
                'record' => $student,
                'compactInbox' => $this->whatsAppInboxCompactLayout(),
                'messagesTabLoaded' => $this->messagesTabLoaded,
                'messageThread' => $this->messageThread,
                'metaSessionOpen' => $this->metaSessionOpen,
                'metaRoutingActive' => $this->metaRoutingActive,
                'whatsappProviderLabel' => $this->whatsappProviderLabel,
                'metaReplyText' => $this->metaReplyText,
                'showMetaReplyAttachment' => $this->showMetaReplyAttachment,
                'metaReplyAttachment' => $this->metaReplyAttachment,
                'waTemplates' => $catalog->selectableTemplates(),
                'waTemplateSyncHint' => $this->whatsAppTemplateSyncHint($catalog),
                'sendWhatsAppTemplateId' => $this->sendWhatsAppTemplateId,
                'sendWhatsAppTemplateFields' => $this->sendWhatsAppTemplateFields,
                'sendWhatsAppTemplateParamCount' => $this->sendWhatsAppTemplateParamCount,
                'sendWhatsAppTemplatePreview' => $this->sendWhatsAppTemplatePreview,
                'sendWhatsAppSelectedTemplateName' => $this->sendWhatsAppSelectedTemplateName,
            ];
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'record' => $this->whatsAppMessageStudent(),
                'compactInbox' => $this->whatsAppInboxCompactLayout(),
                'messagesTabLoaded' => true,
                'messageThread' => [],
                'metaSessionOpen' => false,
                'metaRoutingActive' => false,
                'whatsappProviderLabel' => 'Unavailable',
                'metaReplyText' => $this->metaReplyText,
                'showMetaReplyAttachment' => false,
                'metaReplyAttachment' => null,
                'waTemplates' => collect(),
                'waTemplateSyncHint' => null,
                'sendWhatsAppTemplateId' => null,
                'sendWhatsAppTemplateFields' => [],
                'sendWhatsAppTemplateParamCount' => 0,
                'sendWhatsAppTemplatePreview' => null,
                'sendWhatsAppSelectedTemplateName' => null,
            ];
        }
    }

    protected function whatsAppTemplateSyncHint(WhatsAppTemplateCatalog $catalog): ?string
    {
        if (! $this->metaRoutingActive) {
            return null;
        }

        $orphaned = $catalog->orphanedPalTemplateNames();

        if ($orphaned === []) {
            return null;
        }

        return 'Only Meta-synced templates can be sent. Templates like «'
            .implode('», «', array_slice($orphaned, 0, 3))
            .'» are from an old import — open Connection & Setup and click Sync templates.';
    }

    public function enableMetaReplyAttachment(): void
    {
        $this->showMetaReplyAttachment = true;
    }

    public function loadMessagesTab(): void
    {
        $student = $this->whatsAppMessageStudent();

        if ($this->messagesTabLoaded || ! $student) {
            return;
        }

        $this->messagesTabLoaded = true;

        try {
            $threadService = app(StudentWhatsAppThreadService::class);
            $resolver = app(WhatsAppProviderResolver::class);

            $this->messageThread = $threadService->threadForStudent($student)
                ->map(fn (StudentWhatsAppThreadItem $item): array => $item->toArray())
                ->values()
                ->all();
            $this->metaSessionOpen = $threadService->sessionOpenForStudent($student);
            $this->metaRoutingActive = $resolver->metaOverridesPalDigital();
            $this->whatsappProviderLabel = $resolver->activeProviderLabel();
        } catch (\Throwable $exception) {
            report($exception);

            $this->messageThread = [];
            $this->metaSessionOpen = false;
            $this->metaRoutingActive = false;
            $this->whatsappProviderLabel = 'Unavailable';
        }
    }

    protected function resetMessagesTab(): void
    {
        $this->messagesTabLoaded = false;
        $this->messageThread = [];
        $this->metaReplyText = '';
        $this->metaReplyAttachment = null;
        $this->showMetaReplyAttachment = false;
        $this->sendWhatsAppTemplateId = null;
        $this->refreshWhatsAppTemplateComposer();
    }

    public function sendMetaReply(): void
    {
        $student = $this->whatsAppMessageStudent();

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            Notification::make()->title('WhatsApp module is not enabled')->warning()->send();

            return;
        }

        if (! $student) {
            return;
        }

        $result = app(MetaWhatsAppInboxService::class)->sendReply(
            $student,
            $this->metaReplyText,
            Auth::user(),
        );

        if ($result['status'] !== 'success') {
            Notification::make()
                ->title('Could not send reply')
                ->body((string) ($result['error'] ?? 'Unknown error'))
                ->danger()
                ->send();

            return;
        }

        $this->metaReplyText = '';
        $this->resetMessagesTab();
        $this->loadMessagesTab();
        $this->afterWhatsAppMessageSent();

        Notification::make()
            ->title('Reply sent')
            ->body('Your message was delivered via Meta WhatsApp.')
            ->success()
            ->send();
    }

    public function sendMetaMedia(): void
    {
        $student = $this->whatsAppMessageStudent();

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            Notification::make()->title('WhatsApp module is not enabled')->warning()->send();

            return;
        }

        if (! $student || ! $this->metaReplyAttachment) {
            Notification::make()->title('Choose a file first')->warning()->send();

            return;
        }

        $result = app(MetaWhatsAppInboxService::class)->sendMedia(
            $student,
            $this->metaReplyAttachment,
            $this->metaReplyText,
            Auth::user(),
        );

        if ($result['status'] !== 'success') {
            Notification::make()
                ->title('Could not send attachment')
                ->body((string) ($result['error'] ?? 'Unknown error'))
                ->danger()
                ->send();

            return;
        }

        $this->metaReplyText = '';
        $this->metaReplyAttachment = null;
        $this->resetMessagesTab();
        $this->loadMessagesTab();
        $this->afterWhatsAppMessageSent();

        Notification::make()
            ->title('Attachment sent')
            ->body('Your file was delivered via Meta WhatsApp.')
            ->success()
            ->send();
    }

    public function sendWhatsAppMessage(): void
    {
        $student = $this->whatsAppMessageStudent();

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            Notification::make()
                ->title('WhatsApp module is not enabled')
                ->warning()
                ->send();

            return;
        }

        if (! $student) {
            return;
        }

        if (! $this->sendWhatsAppTemplateId) {
            Notification::make()
                ->title('Choose a template')
                ->warning()
                ->send();

            return;
        }

        if (blank($student->mobile)) {
            Notification::make()
                ->title('No mobile number')
                ->body('Add a mobile number before sending WhatsApp.')
                ->warning()
                ->send();

            return;
        }

        $template = app(WhatsAppTemplateCatalog::class)->findSelectableTemplate((int) $this->sendWhatsAppTemplateId);

        if (! $template) {
            Notification::make()
                ->title('Template not available')
                ->body('This template is not synced for Meta. Open Connection & Setup and click Sync templates.')
                ->danger()
                ->send();

            return;
        }

        if ($this->sendWhatsAppTemplateParamCount > 0) {
            for ($i = 0; $i < $this->sendWhatsAppTemplateParamCount; $i++) {
                if (blank($this->sendWhatsAppTemplateParams[$i] ?? null)) {
                    $label = $this->sendWhatsAppTemplateFields[$i]['label'] ?? ('Parameter '.($i + 1));

                    Notification::make()
                        ->title('Fill all template fields')
                        ->body('Enter a value for «'.$label.'» before sending.')
                        ->warning()
                        ->send();

                    return;
                }
            }
        }

        $recipient = app(WhatsAppCampaignService::class)->sendSingle(
            $student,
            $template,
            Auth::user(),
            $this->sendWhatsAppTemplateParams,
        );

        $this->resetMessagesTab();
        $this->loadMessagesTab();
        $this->afterWhatsAppMessageSent();

        if ($recipient->status === WhatsAppRecipientStatus::Failed) {
            Notification::make()
                ->title('WhatsApp failed')
                ->body($recipient->error_message ?: 'Meta rejected the message. Check Delivery log on the campaign page.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('WhatsApp sent')
            ->body('Message delivered to '.$student->mobile.'.')
            ->success()
            ->send();
    }

    protected function afterWhatsAppMessageSent(): void
    {
        //
    }

    protected function whatsAppInboxCompactLayout(): bool
    {
        return false;
    }
}
