<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\InteractsWithStudentWhatsAppInbox;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\Student;
use App\Services\MetaWhatsAppConversationService;
use App\Services\StudentWhatsAppThreadService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\MetaWhatsAppConversation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WhatsAppInboxPage extends Page
{
    use InteractsWithStudentWhatsAppInbox;
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 12;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppInbox();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::whatsAppInbox();
    }

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public string $search = '';

    public ?int $selectedStudentId = null;

    public ?string $selectedPhone = null;

    /** @var list<array<string, mixed>> */
    public array $conversations = [];

    public bool $inboxLoaded = false;

    public function mount(): void
    {
        $this->initializeWhatsAppInboxState();

        $studentId = request()->query('student');

        if (is_numeric($studentId)) {
            $student = Student::query()->find((int) $studentId);

            if ($student) {
                $this->selectConversation(
                    app(StudentWhatsAppThreadService::class)->normalizePhoneForStorage((string) $student->mobile)
                        ?: (string) $student->id,
                    $student->id,
                );
            }
        } else {
            $this->loadInbox();
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
                ->recentConversations($this->search, 500)
                ->map(fn (MetaWhatsAppConversation $conversation): array => $conversation->toArray())
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            report($exception);
            $this->conversations = [];
        }
    }

    public function selectConversation(string $phone, ?int $studentId = null): void
    {
        try {
            $thread = app(StudentWhatsAppThreadService::class);
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
            $this->loadInbox();
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
                    'selectedPhone' => $this->selectedPhone,
                    'chatStudent' => $this->whatsAppMessageStudent(),
                    'metaRoutingActive' => $this->metaRoutingActive,
                    'metaSessionOpen' => $this->metaSessionOpen,
                    'messagesViewData' => ((filled($this->selectedPhone) || $this->selectedStudentId) && $this->messagesTabLoaded)
                        ? $this->whatsAppMessagesViewData()
                        : null,
                ]),
        ]);
    }
}
