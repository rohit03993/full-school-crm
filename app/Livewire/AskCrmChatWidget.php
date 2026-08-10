<?php

namespace App\Livewire;

use App\Enums\CrmPermission;
use App\Services\AskCrmService;
use App\Services\AskCrmSessionService;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class AskCrmChatWidget extends Component
{
    public bool $open = false;

    public bool $hasActiveSession = false;

    public bool $isSending = false;

    public string $message = '';

    public ?int $lastStudentId = null;

    public ?string $lastStudentName = null;

    /**
     * @var list<array{role: string, text: string}>
     */
    public array $messages = [];

    public function mount(AskCrmSessionService $session): void
    {
        $this->restoreFromSession($session);
    }

    public static function canView(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StudentsView);
    }

    #[On('open-ask-crm')]
    public function openChat(): void
    {
        $this->open = true;
    }

    public function toggle(): void
    {
        if ($this->open) {
            $this->close(app(AskCrmSessionService::class));

            return;
        }

        $this->open = true;
    }

    public function close(?AskCrmSessionService $session = null): void
    {
        $session ??= app(AskCrmSessionService::class);

        $this->open = false;
        $this->endSession($session);
    }

    public function clearStudentContext(AskCrmSessionService $session): void
    {
        $this->lastStudentId = null;
        $this->lastStudentName = null;
        $session->clearStudentContext();
        $this->hasActiveSession = $session->isActive();

        $this->messages[] = [
            'role' => 'assistant',
            'text' => 'Student context cleared. Ask about someone else — include their name in your question.',
        ];

        $session->save($this->messages, null, null);
        $this->dispatch('ask-crm-scroll-bottom');
    }

    public function send(AskCrmService $askCrm, AskCrmSessionService $session): void
    {
        $question = trim($this->message);

        if ($question === '' || ! Auth::user() || $this->isSending) {
            return;
        }

        $this->isSending = true;

        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
        ];
        $this->message = '';
        $this->dispatch('ask-crm-scroll-bottom');

        try {
            $history = array_slice($this->messages, -12);

            $result = $askCrm->ask(Auth::user(), $question, $history, $this->lastStudentId);

            $this->messages[] = [
                'role' => 'assistant',
                'text' => $result['reply'],
            ];

            if ($result['clear_context'] ?? false) {
                $this->lastStudentId = null;
                $this->lastStudentName = null;
            } elseif (filled($result['student_id'] ?? null)) {
                $this->lastStudentId = (int) $result['student_id'];
                $this->lastStudentName = $result['student_name'] ?? $this->lastStudentName;
            }

            if (count($this->messages) > 40) {
                $this->messages = array_values(array_slice($this->messages, -40));
            }

            $this->persistToSession($session);
        } catch (Throwable $exception) {
            Log::warning('Ask CRM widget send failed', [
                'message' => $exception->getMessage(),
            ]);

            $this->messages[] = [
                'role' => 'assistant',
                'text' => 'Sorry — something went wrong while fetching CRM data. Please try again in a moment.',
            ];

            $this->persistToSession($session);
        } finally {
            $this->isSending = false;
            $this->dispatch('ask-crm-scroll-bottom');
        }
    }

    public function askExample(string $question, AskCrmService $askCrm, AskCrmSessionService $session): void
    {
        $this->open = true;
        $this->message = $question;
        $this->send($askCrm, $session);
    }

    protected function restoreFromSession(AskCrmSessionService $session): void
    {
        if (! $session->isActive()) {
            $this->messages = $session->defaultMessages();
            $this->hasActiveSession = false;

            return;
        }

        $data = $session->load();

        $this->messages = is_array($data['messages'] ?? null) && $data['messages'] !== []
            ? $data['messages']
            : $session->defaultMessages();
        $this->lastStudentId = isset($data['last_student_id']) ? (int) $data['last_student_id'] : null;
        $this->lastStudentName = is_string($data['last_student_name'] ?? null)
            ? $data['last_student_name']
            : null;
        $this->hasActiveSession = true;
    }

    protected function persistToSession(AskCrmSessionService $session): void
    {
        $session->save($this->messages, $this->lastStudentId, $this->lastStudentName);
        $this->hasActiveSession = true;
    }

    protected function endSession(AskCrmSessionService $session): void
    {
        $session->clear();
        $this->messages = $session->defaultMessages();
        $this->lastStudentId = null;
        $this->lastStudentName = null;
        $this->message = '';
        $this->hasActiveSession = false;
        $this->isSending = false;
    }

    public function render()
    {
        return view('livewire.ask-crm-chat-widget');
    }
}
