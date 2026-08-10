<?php

namespace App\Livewire;

use App\Enums\CrmPermission;
use App\Services\AskCrmService;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AskCrmChatWidget extends Component
{
    public bool $open = false;

    public string $message = '';

    public ?int $lastStudentId = null;

    /**
     * @var list<array{role: string, text: string}>
     */
    public array $messages = [];

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => "Hi — I’m Ask CRM.\n\nAsk naturally — I remember the student we’re talking about.\n\nExamples:\n• tell me attendance of Ayyush\n• homework for Abhinav Singh\n• then: has he done or not?",
            ],
        ];
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
        $this->open = ! $this->open;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function send(AskCrmService $askCrm): void
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
        }

        if (count($this->messages) > 40) {
            $this->messages = array_values(array_slice($this->messages, -40));
        }
    }

    public function askExample(string $question, AskCrmService $askCrm): void
    {
        $this->open = true;
        $this->message = $question;
        $this->send($askCrm);
    }

    public function render()
    {
        return view('livewire.ask-crm-chat-widget');
    }
}
