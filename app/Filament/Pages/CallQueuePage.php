<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HandlesLogCallModal;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Models\Student;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Services\CallLogService;
use App\Services\CallQueueService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CallQueuePage extends Page
{
    use HandlesLogCallModal;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Call Queue';

    protected static ?string $title = 'Call Queue';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_CALLS;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::LeadsCall);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('call.queue');
    }

    public ?int $currentStudentId = null;

    /**
     * @var array<string, mixed>
     */
    public array $stats = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $currentLead = null;

    public function mount(CallQueueService $queue): void
    {
        $this->refreshQueue($queue);
    }

    protected function pendingCallMatchesStudent(int $studentId): bool
    {
        return (int) $this->currentStudentId === $studentId;
    }

    public function refreshQueue(CallQueueService $queue): void
    {
        $staff = Auth::user();
        $this->stats = $queue->todayStats($staff);

        $students = $queue->todayQueue($staff);
        $current = $this->currentStudentId
            ? $students->firstWhere('id', $this->currentStudentId)
            : $students->first();

        if (! $current && $students->isNotEmpty()) {
            $current = $students->first();
        }

        $this->currentStudentId = $current?->id;
        $this->currentLead = $current ? $queue->leadPayload($current) : null;
    }

    public function skipLead(CallQueueService $queue): void
    {
        $students = $queue->todayQueue(Auth::user());
        $foundCurrent = false;

        foreach ($students as $student) {
            if ($foundCurrent) {
                $this->currentStudentId = $student->id;
                $this->currentLead = $queue->leadPayload($student);
                $this->showLogCallModal = false;

                return;
            }

            if ((int) $student->id === (int) $this->currentStudentId) {
                $foundCurrent = true;
            }
        }

        $this->currentStudentId = null;
        $this->currentLead = null;
        $this->showLogCallModal = false;
        $this->stats = $queue->todayStats(Auth::user());

        Notification::make()
            ->title('Queue complete')
            ->body('No more leads in today\'s call queue.')
            ->success()
            ->send();
    }

    public function submitLogCall(CallLogService $callLog, CallQueueService $queue): void
    {
        if (! $this->currentStudentId) {
            return;
        }

        $student = Student::query()->findOrFail($this->currentStudentId);

        if (! $this->persistLogCall($student, $callLog)) {
            return;
        }

        $this->showLogCallModal = false;
        $this->resetLogCallForm();

        Notification::make()
            ->title('Call logged')
            ->success()
            ->send();

        $this->skipLead($queue);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.call-queue')
                ->viewData(fn (): array => [
                    'stats' => $this->stats,
                    'currentLead' => $this->currentLead,
                    'showLogCallModal' => $this->showLogCallModal,
                    'logCallForm' => $this->logCallForm,
                    'logCallModalMode' => 'queue',
                    'logCallLeadName' => $this->currentLead['name'] ?? null,
                    'logCallLeadPhone' => $this->currentLead['mobile_display'] ?? null,
                ]),
        ]);
    }
}
