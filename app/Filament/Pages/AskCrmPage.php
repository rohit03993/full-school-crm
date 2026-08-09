<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\AskCrmService;
use App\Support\CrmNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AskCrmPage extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::StudentsView;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Ask CRM';

    protected static ?string $title = 'Ask CRM';

    protected static ?string $slug = 'ask-crm';

    protected static ?int $navigationSort = 3;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    protected string $view = 'filament.pages.ask-crm';

    public string $message = '';

    /**
     * @var list<array{role: string, text: string}>
     */
    public array $messages = [];

    public function getSubheading(): ?string
    {
        return 'Ask about attendance, fees, or homework using student names from your CRM.';
    }

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => "Hi — I’m Ask CRM.\n\n"
                    ."Ask me things like:\n"
                    ."• What is Ayyush’s attendance today?\n"
                    ."• What is Ayyush’s attendance this month?\n"
                    ."• How much fee is pending for Ayyush?\n"
                    .'• How many homework Not Done for Ayyush this week?',
            ],
        ];
    }

    public function send(AskCrmService $askCrm): void
    {
        $question = trim($this->message);

        if ($question === '') {
            Notification::make()->title('Type a question first')->warning()->send();

            return;
        }

        $user = Auth::user();

        if (! $user) {
            return;
        }

        $this->messages[] = [
            'role' => 'user',
            'text' => $question,
        ];
        $this->message = '';

        $result = $askCrm->ask($user, $question);

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
        $this->message = $question;
        $this->send($askCrm);
    }
}
