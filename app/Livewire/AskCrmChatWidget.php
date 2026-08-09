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

    /**
     * @var list<array{role: string, text: string}>
     */
    public array $messages = [];

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => "Hi — I’m Ask CRM.\n\nAsk about attendance, fees, or homework — for example:\n• What is Ayyush’s attendance today?",
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

        $result = $askCrm->ask(Auth::user(), $question);

        $this->messages[] = [
            'role' => 'assistant',
            'text' => $result['reply'],
        ];

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
