<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\Student;
use App\Services\MetaWhatsAppConversationService;
use App\Services\StudentWhatsAppThreadService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\MetaWhatsAppConversation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Attributes\On;
use UnitEnum;

class WhatsAppInboxPage extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappInbox;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static bool $shouldRegisterNavigation = false;

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

    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function getHeader(): ?ViewContract
    {
        return view('filament.pages.partials.student-profile-no-page-header');
    }

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public string $search = '';

    public ?int $selectedStudentId = null;

    public ?string $selectedPhone = null;

    /** @var list<array<string, mixed>> */
    public array $conversations = [];

    public bool $inboxLoaded = false;

    /** @var 'all'|'pending'|'failed' */
    public string $listFilter = 'all';

    public ?string $inboxChangeStamp = null;

    public bool $composerLocked = false;

    public function mount(): void
    {
        $this->loadInbox();

        $studentId = request()->query('student');

        if (! is_numeric($studentId)) {
            return;
        }

        $student = Student::query()->find((int) $studentId);

        if ($student) {
            $this->selectConversation(
                app(StudentWhatsAppThreadService::class)->normalizePhoneForStorage((string) $student->mobile)
                    ?: (string) $student->id,
                $student->id,
            );
        }
    }

    public function updatedSearch(): void
    {
        $this->loadInbox();
    }

    public function setListFilter(string $filter): void
    {
        $this->listFilter = match ($filter) {
            'pending' => 'pending',
            'failed' => 'failed',
            default => 'all',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function visibleConversations(): array
    {
        if ($this->listFilter === 'pending') {
            return array_values(array_filter(
                $this->conversations,
                fn (array $conversation): bool => (bool) ($conversation['needs_reply'] ?? false),
            ));
        }

        if ($this->listFilter === 'failed') {
            return array_values(array_filter(
                $this->conversations,
                fn (array $conversation): bool => (bool) ($conversation['last_send_failed'] ?? false),
            ));
        }

        return $this->conversations;
    }

    public function pendingReplyCount(): int
    {
        return count(array_filter(
            $this->conversations,
            fn (array $conversation): bool => (bool) ($conversation['needs_reply'] ?? false),
        ));
    }

    public function failedSendCount(): int
    {
        return count(array_filter(
            $this->conversations,
            fn (array $conversation): bool => (bool) ($conversation['last_send_failed'] ?? false),
        ));
    }

    public function pollInbox(): void
    {
        if ($this->composerLocked) {
            $this->skipRender();

            return;
        }

        $stamp = app(MetaWhatsAppConversationService::class)->inboxChangeStamp();

        if ($stamp === $this->inboxChangeStamp) {
            $this->skipRender();

            return;
        }

        $this->loadInbox();
    }

    public function loadInbox(): void
    {
        $this->inboxLoaded = true;

        try {
            $service = app(MetaWhatsAppConversationService::class);
            $this->conversations = $service
                ->recentConversations($this->search, 500)
                ->map(fn (MetaWhatsAppConversation $conversation): array => $conversation->toArray())
                ->values()
                ->all();
            $this->inboxChangeStamp = $service->inboxChangeStamp();
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
        } catch (\Throwable $exception) {
            report($exception);

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
        $this->composerLocked = false;
        $this->loadInbox();
    }

    #[On('whatsapp-chat-opened')]
    public function rememberOpenChat(?string $phone = null, mixed $studentId = null): void
    {
        $this->selectedPhone = filled($phone) ? $phone : null;
        $this->selectedStudentId = is_numeric($studentId) ? (int) $studentId : null;
        $this->skipRender();
    }

    #[On('whatsapp-chat-closed')]
    public function rememberClosedChat(): void
    {
        $this->selectedPhone = null;
        $this->selectedStudentId = null;
        $this->composerLocked = false;
        $this->skipRender();
    }

    #[On('whatsapp-composer-lock')]
    public function rememberComposerLock(bool $locked = false): void
    {
        $this->composerLocked = $locked;
        $this->skipRender();
    }

    #[On('whatsapp-inbox-refresh-list')]
    public function refreshInboxList(): void
    {
        $this->loadInbox();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.whatsapp-inbox')
                ->viewData(fn (): array => [
                    'search' => $this->search,
                    'inboxLoaded' => $this->inboxLoaded,
                    'listFilter' => $this->listFilter,
                    'pendingReplyCount' => $this->pendingReplyCount(),
                    'failedSendCount' => $this->failedSendCount(),
                    'totalConversationCount' => count($this->conversations),
                    'conversations' => $this->visibleConversations(),
                    'selectedStudentId' => $this->selectedStudentId,
                    'selectedPhone' => $this->selectedPhone,
                ]),
        ]);
    }
}
