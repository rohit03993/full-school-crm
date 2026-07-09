<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FinishesAttendanceSave;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use App\Models\BatchStudent;
use App\Services\ActivityAttendanceService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\CrmHint;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ActivityAttendancePage extends Page
{
    use FinishesAttendanceSave;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Enter Test Marks';

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks) && ! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return false;
        }

        return CrmAccess::canAny(
            Auth::user(),
            CrmPermission::MarksImport,
            CrmPermission::AttendanceWorkshops,
        );
    }

    public ?int $activityId = null;

    /**
     * @var array<int, bool>
     */
    public array $marks = [];

    /**
     * @var array<int, array{marks_obtained: ?string, grade: ?string, remarks: ?string}>
     */
    public array $scoreMarks = [];

    public bool $rosterLoaded = false;

    public bool $supportsScoring = false;

    public ?float $maxMarks = null;

    public ?string $activityTitle = null;

    public ?string $examName = null;

    public ?string $subjectName = null;

    public ?string $batchLabel = null;

    public ?string $sessionDateLabel = null;

    public ?int $examWindowId = null;

    public int $enteredMarksCount = 0;

    /**
     * @var Collection<int, BatchStudent>
     */
    public Collection $roster;

    public function mount(): void
    {
        $this->roster = new Collection;

        $id = request()->query('id');

        if (filled($id)) {
            $this->activityId = (int) $id;
            $this->loadRoster();
        }
    }

    public function loadRoster(): void
    {
        $id = $this->activityId;

        if (! $id) {
            $this->roster = new Collection;
            $this->marks = [];
            $this->scoreMarks = [];
            $this->rosterLoaded = false;
            $this->activityTitle = null;
            $this->examName = null;
            $this->subjectName = null;
            $this->batchLabel = null;
            $this->sessionDateLabel = null;
            $this->examWindowId = null;
            $this->enteredMarksCount = 0;
            $this->supportsScoring = false;
            $this->maxMarks = null;

            return;
        }

        $activity = app(ActivityAttendanceService::class)->resolve($id);
        $activity->loadMissing(['batch.academicSession', 'batch.course']);

        $this->activityTitle = $activity->displayTitle();
        $this->subjectName = filled($activity->metadataValue('subject'))
            ? (string) $activity->metadataValue('subject')
            : null;
        $this->examName = filled($activity->metadataValue('test_name'))
            ? (string) $activity->metadataValue('test_name')
            : $activity->title;
        $this->batchLabel = $activity->batch
            ? ClassSectionLabel::forBatch($activity->batch, includeSession: false)
            : null;
        $this->sessionDateLabel = $activity->session_date?->format('d M Y');
        $this->examWindowId = (int) ($activity->metadataValue('exam_window_id') ?? 0) ?: null;
        $this->rosterLoaded = true;
        $this->supportsScoring = (bool) $activity->activityType?->supportsScoring();
        $maxMarks = $activity->metadataValue('max_marks');
        $this->maxMarks = filled($maxMarks) ? (float) $maxMarks : null;

        $this->roster = BatchStudent::query()
            ->where('batch_students.batch_id', $activity->batch_id)
            ->where('batch_students.is_active', true)
            ->join('students', 'students.id', '=', 'batch_students.student_id')
            ->orderBy('students.name')
            ->select('batch_students.*')
            ->with('student')
            ->get();

        $existing = app(ActivityAttendanceService::class)->marksFor($activity);
        $scores = app(ActivityAttendanceService::class)->scoresFor($activity);

        $this->marks = [];
        $this->scoreMarks = [];

        foreach ($this->roster as $row) {
            $studentId = $row->student_id;
            $this->marks[$studentId] = (bool) ($existing[$studentId] ?? false);
            $this->scoreMarks[$studentId] = [
                'marks_obtained' => $scores[$studentId]['marks_obtained'] ?? null,
                'grade' => $scores[$studentId]['grade'] ?? null,
                'remarks' => $scores[$studentId]['remarks'] ?? null,
            ];
        }

        $this->enteredMarksCount = collect($this->scoreMarks)
            ->filter(fn (array $row): bool => filled($row['marks_obtained'] ?? null))
            ->count();
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = true;
        }
    }

    public function saveAttendance(ActivityAttendanceService $attendance, array $marks = []): void
    {
        if ($marks !== []) {
            $this->marks = $this->normalizeBooleanMarksFromClient($marks);
        }

        $id = $this->activityId;

        if (! $id) {
            Notification::make()->title('Invalid activity')->warning()->send();

            return;
        }

        try {
            $activity = $attendance->resolve($id);
            $saved = $attendance->saveMarks($activity, $this->marks, Auth::user(), $this->scoreMarks);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not save')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Check marks and try again.')
                ->danger()
                ->send();

            return;
        }

        $examWindowId = (int) ($activity->metadataValue('exam_window_id') ?? 0);
        $redirectTo = $examWindowId > 0
            ? ExamWindowPage::getUrl(['window' => $examWindowId])
            : ActivitySessionResource::getUrl('index');

        $this->finishAttendanceSave(
            'Saved',
            "{$saved} student record(s) saved.",
            $redirectTo,
        );
    }

    public function getHeading(): string
    {
        if ($this->supportsScoring) {
            if (filled($this->subjectName)) {
                return $this->subjectName;
            }

            return $this->examName ?? 'Enter marks';
        }

        if ($this->activityTitle) {
            return 'Mark attendance · '.$this->activityTitle;
        }

        return 'Mark Activity Attendance';
    }

    public function getSubheading(): ?string
    {
        if ($this->supportsScoring) {
            $parts = array_filter([
                $this->examName,
                $this->batchLabel,
                $this->sessionDateLabel,
            ]);

            if ($parts !== []) {
                return implode(' · ', $parts);
            }
        }

        return CrmHint::text('activity.attendance');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.activity-attendance-roster')
                ->viewData(fn (): array => [
                    'rosterLoaded' => $this->rosterLoaded,
                    'roster' => $this->roster,
                    'marks' => $this->marks,
                    'scoreMarks' => $this->scoreMarks,
                    'supportsScoring' => $this->supportsScoring,
                    'maxMarks' => $this->maxMarks,
                    'examName' => $this->examName,
                    'subjectName' => $this->subjectName,
                    'batchLabel' => $this->batchLabel,
                    'sessionDateLabel' => $this->sessionDateLabel,
                    'enteredMarksCount' => $this->enteredMarksCount,
                    'examWindowBackUrl' => $this->examWindowId
                        ? ExamWindowPage::getUrl(['window' => $this->examWindowId])
                        : null,
                ]),
        ]);
    }
}
