<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\StaffAttendance;
use App\Models\StaffLoginSession;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaffActivityTimelineService
{
    public const PAGE = 50;

    public const MAX = 200;

    public function __construct(
        protected StaffActivityService $activity,
    ) {}

    /**
     * Newest events for one person in the selected range. Capped so a year view
     * never loads the full history.
     *
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function forStaff(User $subject, StaffActivityRange $range, int $limit = self::PAGE): array
    {
        $limit = max(self::PAGE, min(self::MAX, $limit));
        $perSource = min(80, $limit);
        [$start, $end] = $range->bounds();

        $items = collect()
            ->merge($this->sessions($subject, $start, $end, $perSource))
            ->merge($this->punches($subject, $start, $end))
            ->merge($this->fromActivity($subject, $range, $perSource))
            ->merge($this->studentEdits($subject, $start, $end, $perSource))
            ->filter(fn (array $item): bool => $item['occurred_at'] instanceof Carbon)
            ->sortByDesc(fn (array $item): int => $item['occurred_at']->getTimestamp())
            ->values();

        return [
            'items' => $items->take($limit)->map(fn (array $item): array => $this->present($item))->all(),
            'has_more' => $items->count() >= $limit,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function sessions(User $subject, Carbon $start, Carbon $end, int $limit): array
    {
        $rows = StaffLoginSession::query()
            ->where('user_id', $subject->id)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('logged_in_at', [$start, $end])
                    ->orWhereBetween('logged_out_at', [$start, $end]);
            })
            ->orderByDesc('logged_in_at')
            ->limit($limit)
            ->get(['id', 'logged_in_at', 'logged_out_at']);

        $items = [];

        foreach ($rows as $row) {
            if ($row->logged_in_at && $row->logged_in_at->between($start, $end)) {
                $items[] = $this->item('login-'.$row->id, 'login', 'Login', 'Logged in to the CRM', null, $row->logged_in_at);
            }

            if ($row->logged_out_at && $row->logged_out_at->between($start, $end)) {
                $items[] = $this->item('logout-'.$row->id, 'logout', 'Logout', 'Logged out', null, $row->logged_out_at);
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function punches(User $subject, Carbon $start, Carbon $end): array
    {
        $rows = StaffAttendance::query()
            ->where('user_id', $subject->id)
            ->whereDate('attendance_date', '>=', $start->toDateString())
            ->whereDate('attendance_date', '<=', $end->toDateString())
            ->orderByDesc('attendance_date')
            ->limit(40)
            ->get(['id', 'attendance_date', 'checked_in_at', 'checked_out_at', 'status']);

        $items = [];

        foreach ($rows as $row) {
            $in = $row->checked_in_at;
            $out = $row->checked_out_at;

            if ($in && $in->between($start, $end)) {
                $items[] = $this->item('punch-in-'.$row->id, 'punch', 'Attendance', 'Punched in', $row->status?->label(), $in);
            } elseif (! $in) {
                $at = $row->attendance_date?->copy()->startOfDay();
                if ($at) {
                    $items[] = $this->item('punch-day-'.$row->id, 'punch', 'Attendance', 'Attendance marked', $row->status?->label(), $at);
                }
            }

            if ($out && $out->between($start, $end)) {
                $items[] = $this->item('punch-out-'.$row->id, 'punch', 'Attendance', 'Punched out', null, $out);
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fromActivity(User $subject, StaffActivityRange $range, int $limit): array
    {
        $items = [];

        foreach (StaffActivityType::cases() as $type) {
            if (in_array($type, [StaffActivityType::Attendance, StaffActivityType::AttendanceMarked], true)) {
                continue;
            }

            if (! $this->activity->visible($subject, $type)) {
                continue;
            }

            $rows = $this->activity->query($subject, $type, $range)->limit($limit)->get();

            foreach ($rows as $row) {
                $items[] = $this->fromRow($type, $row);
            }
        }

        if ($this->activity->visible($subject, StaffActivityType::AttendanceMarked)) {
            $items = array_merge($items, $this->classMarks($subject, $range, $limit));
        }

        return array_values(array_filter($items));
    }

    /**
     * One line per class, not one line per student.
     *
     * @return list<array<string, mixed>>
     */
    protected function classMarks(User $subject, StaffActivityRange $range, int $limit): array
    {
        [$start, $end] = $range->bounds();

        $rows = Attendance::query()
            ->leftJoin('batches', 'batches.id', '=', 'attendances.batch_id')
            ->where('attendances.marked_by_user_id', $subject->id)
            ->whereIn('attendances.punch_source', ['manual', 'roll_call'])
            ->whereDate('attendances.attendance_date', '>=', $start->toDateString())
            ->whereDate('attendances.attendance_date', '<=', $end->toDateString())
            ->groupBy('attendances.batch_id', 'attendances.attendance_date', 'batches.name')
            ->orderByDesc('marked_at')
            ->limit($limit)
            ->get([
                'attendances.batch_id',
                'attendances.attendance_date',
                'batches.name as batch_name',
                DB::raw('COUNT(*) as marked_count'),
                DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                DB::raw('MIN(attendances.created_at) as marked_at'),
            ]);

        $items = [];

        foreach ($rows as $row) {
            $at = $row->marked_at ? Carbon::parse($row->marked_at) : Carbon::parse($row->attendance_date);
            if (! $at->between($start, $end) && $row->attendance_date) {
                $at = Carbon::parse($row->attendance_date)->startOfDay();
            }

            $items[] = $this->item(
                'class-'.$row->batch_id.'-'.$row->attendance_date,
                'attendance',
                'Attendance',
                'Marked attendance',
                trim(($row->batch_name ?? 'Class').' · '.((int) $row->present_count).' present'),
                $at,
            );
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function studentEdits(User $subject, Carbon $start, Carbon $end, int $limit): array
    {
        if (! CrmAccess::can($subject, CrmPermission::StudentsEdit)) {
            return [];
        }

        $logs = AuditLog::query()
            ->where('user_id', $subject->id)
            ->where('action', 'Student Profile Updated')
            ->where('auditable_type', Student::class)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'auditable_id', 'created_at']);

        if ($logs->isEmpty()) {
            return [];
        }

        $names = Student::query()
            ->whereIn('id', $logs->pluck('auditable_id')->filter()->all())
            ->pluck('name', 'id');

        return $logs->map(function (AuditLog $log) use ($names): array {
            $studentId = (int) $log->auditable_id;

            return $this->item(
                'edit-'.$log->id,
                'edit',
                'Edit',
                'Edited student details',
                $names[$studentId] ?? 'Student',
                $log->created_at,
                $studentId > 0 ? StudentProfilePage::getUrl(['record' => $studentId]) : null,
            );
        })->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fromRow(StaffActivityType $type, mixed $row): ?array
    {
        return match ($type) {
            StaffActivityType::Calls => $this->item(
                'call-'.$row->id,
                'call',
                'Call',
                'Logged a call',
                collect([$row->student?->name, $row->call_status?->label()])->filter()->implode(' · '),
                $row->called_at,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::InboxMessages, StaffActivityType::HomeworkMessages => $this->item(
                $type->value.'-'.$row->id,
                'whatsapp',
                'WhatsApp',
                $type === StaffActivityType::HomeworkMessages ? 'Sent a homework message' : 'Sent an inbox message',
                $row->student?->name ?: 'Student',
                $row->created_at,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::Campaigns => $this->item(
                'campaign-'.$row->id,
                'whatsapp',
                'WhatsApp',
                'Sent a campaign',
                trim(($row->name ?: 'Campaign').' · '.((int) $row->sent_count).' sent'),
                $row->shot_at ?? $row->created_at,
            ),
            StaffActivityType::FeeNotices => $this->item(
                'notice-'.$row->id,
                'whatsapp',
                'WhatsApp',
                'Sent a fee notice',
                collect([$row->student?->name, '₹'.number_format((float) $row->amount, 0)])->filter()->implode(' · '),
                $row->sent_at,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::Homework => $this->item(
                'homework-'.$row->id,
                'homework',
                'Homework',
                'Assigned homework',
                collect([$row->title, $row->batch?->name])->filter()->implode(' · '),
                $row->created_at,
            ),
            StaffActivityType::FeesCollected => $this->item(
                'fee-'.$row->id,
                'fee',
                'Fees',
                'Collected a fee',
                collect([$row->student?->name, '₹'.number_format((float) $row->amount, 0)])->filter()->implode(' · '),
                $row->created_at ?? $row->payment_date,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::FeeChanges => $this->item(
                'fee-change-'.$row->id,
                'fee',
                'Fees',
                'Changed a fee plan',
                $row->feeStructure?->enrollment?->student?->name,
                $row->changed_at,
                $this->profileUrl($row->feeStructure?->enrollment?->student_id),
            ),
            StaffActivityType::AdmissionsApproved => $this->item(
                'admission-'.$row->id,
                'admission',
                'Admission',
                'Approved an admission',
                $row->student?->name,
                $row->approved_at,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::Certificates => $this->item(
                'certificate-'.$row->id,
                'certificate',
                'Certificate',
                'Issued a certificate',
                collect([$row->student?->name, $row->type?->label()])->filter()->implode(' · '),
                $row->created_at ?? $row->issued_on,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::CasesOpened => $this->item(
                'case-'.$row->id,
                'case',
                'Case',
                'Opened a case',
                collect([$row->student?->name, $row->title])->filter()->implode(' · '),
                $row->opened_at,
                $this->profileUrl($row->student_id),
            ),
            StaffActivityType::Visits => $this->item(
                'visit-'.$row->id,
                'visit',
                'Visit',
                'Logged a visit',
                $row->student?->name,
                $row->created_at ?? $row->visit_date,
                $this->profileUrl($row->student_id),
            ),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function item(
        string $id,
        string $type,
        string $category,
        string $title,
        ?string $summary,
        mixed $at,
        ?string $url = null,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'summary' => $summary,
            'occurred_at' => $at instanceof Carbon ? $at : ($at ? Carbon::parse($at) : null),
            'url' => $url,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function present(array $item): array
    {
        /** @var Carbon $at */
        $at = $item['occurred_at'];

        return [
            'id' => $item['id'],
            'type' => $item['type'],
            'category' => $item['category'],
            'title' => $item['title'],
            'summary' => $item['summary'],
            'url' => $item['url'],
            'occurred_at_label' => $at->format('h:i A'),
            'occurred_date' => $at->toDateString(),
            'occurred_date_label' => $at->isToday() ? 'Today' : $at->format('d M Y'),
        ];
    }

    protected function profileUrl(mixed $studentId): ?string
    {
        if (! filled($studentId)) {
            return null;
        }

        try {
            return StudentProfilePage::getUrl(['record' => (int) $studentId]);
        } catch (\Throwable) {
            return null;
        }
    }
}
