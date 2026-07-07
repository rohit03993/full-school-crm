<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\InteractsWithStudentWhatsAppInbox;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\Student;
use App\Services\MetaWhatsAppConversationService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\MetaWhatsAppConversation;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use UnitEnum;

class WhatsAppInboxPage extends Page
{
    use InteractsWithStudentWhatsAppInbox;
    use RequiresCrmPermission;
    use WithFileUploads;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = 'Inbox';

    protected static ?string $title = 'WhatsApp Inbox';

    protected static ?int $navigationSort = 12;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public string $search = '';

    public ?int $selectedStudentId = null;

    /** @var list<array<string, mixed>> */
    public array $conversations = [];

    public bool $inboxLoaded = false;

    public function mount(): void
    {
        $this->initializeWhatsAppInboxState();

        $studentId = request()->query('student');

        if (is_numeric($studentId)) {
            $this->selectedStudentId = (int) $studentId;
        }

        $this->loadInbox();

        if ($this->selectedStudentId) {
            $this->selectConversation($this->selectedStudentId);
        }
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('meta_whatsapp.inbox');
    }

    public function updatedSearch(): void
    {
        $this->loadInbox();
    }

    public function loadInbox(): void
    {
        $this->inboxLoaded = true;

        try {
            $this->conversations = app(MetaWhatsAppConversationService::class)
                ->recentConversations($this->search)
                ->map(fn (MetaWhatsAppConversation $conversation): array => $conversation->toArray())
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            report($exception);
            $this->conversations = [];
        }
    }

    public function selectConversation(int $studentId): void
    {
        try {
            $this->selectedStudentId = $studentId;
            $this->resetMessagesTab();
            $this->loadMessagesTab();
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Could not open chat')
                ->body('Please refresh the page. If this continues, run migrations on the server.')
                ->danger()
                ->send();
        }
    }

    protected function whatsAppMessageStudent(): ?Student
    {
        if (! $this->selectedStudentId) {
            return null;
        }

        return Student::query()->find($this->selectedStudentId);
    }

    protected function afterWhatsAppMessageSent(): void
    {
        $this->loadInbox();
    }

    protected function whatsAppInboxCompactLayout(): bool
    {
        return true;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.whatsapp-inbox')
                ->viewData(fn (): array => [
                    'search' => $this->search,
                    'inboxLoaded' => $this->inboxLoaded,
                    'conversations' => $this->conversations,
                    'selectedStudentId' => $this->selectedStudentId,
                    'chatStudent' => $this->whatsAppMessageStudent(),
                    'metaRoutingActive' => $this->metaRoutingActive,
                    'metaSessionOpen' => $this->metaSessionOpen,
                    'messagesViewData' => $this->selectedStudentId ? $this->whatsAppMessagesViewData() : null,
                ]),
        ]);
    }
}
