<?php

namespace App\Filament\Pages;

use App\Enums\ActivityKind;
use App\Models\BatchStudent;
use App\Services\ActivityAttendanceService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ActivityAttendancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Mark Activity Attendance';

    public ?string $activityKind = null;

    public ?int $activityId = null;

    /**
     * @var array<int, bool>
     */
    public array $marks = [];

    public bool $rosterLoaded = false;

    public ?string $activityTitle = null;

    /**
     * @var Collection<int, BatchStudent>
     */
    public Collection $roster;

    public function mount(): void
    {
        $this->roster = new Collection;

        $kind = request()->query('kind');
        $id = request()->query('id');

        if (is_string($kind) && filled($id)) {
            $this->activityKind = $kind;
            $this->activityId = (int) $id;
            $this->loadRoster();
        }
    }

    public function loadRoster(): void
    {
        $kind = ActivityKind::tryFrom((string) $this->activityKind);
        $id = $this->activityId;

        if (! $kind || ! $id) {
            $this->roster = new Collection;
            $this->marks = [];
            $this->rosterLoaded = false;
            $this->activityTitle = null;

            return;
        }

        $activity = app(ActivityAttendanceService::class)->resolve($kind, $id);
        $this->activityTitle = $activity->displayTitle();
        $this->rosterLoaded = true;

        $this->roster = BatchStudent::query()
            ->where('batch_students.batch_id', $activity->batch_id)
            ->where('batch_students.is_active', true)
            ->join('students', 'students.id', '=', 'batch_students.student_id')
            ->orderBy('students.name')
            ->select('batch_students.*')
            ->with('student')
            ->get();

        $existing = app(ActivityAttendanceService::class)->marksFor($activity);

        $this->marks = [];

        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = (bool) ($existing[$row->student_id] ?? false);
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = true;
        }
    }

    public function saveAttendance(ActivityAttendanceService $attendance): void
    {
        $kind = ActivityKind::tryFrom((string) $this->activityKind);
        $id = $this->activityId;

        if (! $kind || ! $id) {
            Notification::make()->title('Invalid activity')->warning()->send();

            return;
        }

        $activity = $attendance->resolve($kind, $id);
        $saved = $attendance->saveMarks($activity, $this->marks, Auth::user());

        Notification::make()
            ->title('Attendance saved')
            ->body("{$saved} student record(s) saved.")
            ->success()
            ->send();
    }

    public function getHeading(): string
    {
        return $this->activityTitle ?? 'Mark Activity Attendance';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.activity-attendance-roster')
                ->viewData(fn (): array => [
                    'rosterLoaded' => $this->rosterLoaded,
                    'roster' => $this->roster,
                    'marks' => $this->marks,
                    'activityTitle' => $this->activityTitle,
                ]),
        ]);
    }
}
