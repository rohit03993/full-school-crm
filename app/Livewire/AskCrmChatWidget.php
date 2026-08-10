<?php

namespace App\Livewire;

use App\Enums\CrmPermission;
use App\Services\AskCrmService;
use App\Services\AskCrmSessionService;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AskCrmChatWidget extends Component
{
    public bool $open = false;

    public bool $hasActiveSession = false;

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

    public function send(AskCrmService $askCrm, AskCrmSessionService $session): void
    {
        $question = trim($this->message);

        if ($question === '' || ! Auth::user()) {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
        ];
        $this->message = '';

        $history = array_slice($this->messages, -12);

        $result = $askCrm->ask(Auth::user(), $question, $history, $this->lastStudentId);

        $this->messages[] = [
            'role' => 'assistant',
            'text' => $result['reply'],
        ];

        if (filled($result['student_id'] ?? null)) {
            $this->lastStudentId = (int) $result['student_id'];
            $this->lastStudentName = $result['student_name'] ?? $this->lastStudentName;
        }

        if (count($this->messages) > 40) {
            $this->messages = array_values(array_slice($this->messages, -40));
        }

        $this->persistToSession($session);
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
    }

    public function render()
    {
        return view('livewire.ask-crm-chat-widget');
    }
}
