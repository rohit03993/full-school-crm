<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendancePunchWhatsappLog;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Support\AttendanceSourceLabel;
use App\Support\ClassSectionLabel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AttendanceHubOverviewService
{
    public const FEED_PER_PAGE = 10;

    /**
     * @return array{
     *     date: string,
     *     date_label: string,
     *     students_expected: int,
     *     students_present: int,
     *     students_absent: int,
     *     students_leave: int,
     *     students_marked: int,
     *     students_unmarked: int,
     *     staff_expected: int,
     *     staff_present: int,
     *     staff_absent: int,
     *     staff_leave: int,
     *     staff_marked: int,
     *     staff_unmarked: int,
     *     class_rows: list<array{batch_id: int, name: string, expected: int, present: int, absent: int, leave: int, unmarked: int}>
     * }
     */
    public function overview(string $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        $studentsExpected = (int) BatchStudent::query()->where('is_active', true)->count();

        $studentsPresent = (int) Attendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Present)
            ->count();
        $studentsAbsent = (int) Attendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Absent)
            ->count();
        $studentsLeave = (int) Attendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Leave)
            ->count();
        $studentsMarked = $studentsPresent + $studentsAbsent + $studentsLeave;

        $staffExpected = (int) StaffProfile::query()
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->count();

        $staffPresent = (int) StaffAttendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Present)
            ->count();
        $staffAbsent = (int) StaffAttendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Absent)
            ->count();
        $staffLeave = (int) StaffAttendance::query()
            ->whereDate('attendance_date', $day)
            ->where('status', AttendanceStatus::Leave)
            ->count();
        $staffMarked = $staffPresent + $staffAbsent + $staffLeave;

        return [
            'date' => $day,
            'date_label' => Carbon::parse($day)->format('d M Y'),
            'students_expected' => $studentsExpected,
            'students_present' => $studentsPresent,
            'students_absent' => $studentsAbsent,
            'students_leave' => $studentsLeave,
            'students_marked' => $studentsMarked,
            'students_unmarked' => max(0, $studentsExpected - $studentsMarked),
            'staff_expected' => $staffExpected,
            'staff_present' => $staffPresent,
            'staff_absent' => $staffAbsent,
            'staff_leave' => $staffLeave,
            'staff_marked' => $staffMarked,
            'staff_unmarked' => max(0, $staffExpected - $staffMarked),
            'class_rows' => $this->classRows($day),
        ];
    }

    /**
     * @return list<array{batch_id: int, name: string, expected: int, present: int, absent: int, leave: int, unmarked: int}>
     */
    protected function classRows(string $day): array
    {
        $batches = Batch::query()
            ->withCount(['activeStudents'])
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($batches as $batch) {
            $expected = (int) $batch->active_students_count;
            if ($expected === 0) {
                continue;
            }

            $present = (int) Attendance::query()
                ->where('batch_id', $batch->id)
                ->whereDate('attendance_date', $day)
                ->where('status', AttendanceStatus::Present)
                ->count();
            $absent = (int) Attendance::query()
                ->where('batch_id', $batch->id)
                ->whereDate('attendance_date', $day)
                ->where('status', AttendanceStatus::Absent)
                ->count();
            $leave = (int) Attendance::query()
                ->where('batch_id', $batch->id)
                ->whereDate('attendance_date', $day)
                ->where('status', AttendanceStatus::Leave)
                ->count();
            $marked = $present + $absent + $leave;

            $rows[] = [
                'batch_id' => $batch->id,
                'name' => ClassSectionLabel::forBatch($batch, includeSession: false, includeShift: false),
                'expected' => $expected,
                'present' => $present,
                'absent' => $absent,
                'leave' => $leave,
                'unmarked' => max(0, $expected - $marked),
            ];
        }

        return $rows;
    }

    /**
     * Unified student + staff attendance rows for the hub feed.
     *
     * @param  'all'|'student'|'staff'  $type
     */
    public function feed(string $date, string $type = 'all', int $page = 1, int $perPage = self::FEED_PER_PAGE): LengthAwarePaginator
    {
        $day = Carbon::parse($date)->toDateString();
        $type = in_array($type, ['all', 'student', 'staff'], true) ? $type : 'all';
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $items = collect();

        if ($type === 'all' || $type === 'student') {
            $items = $items->concat($this->studentFeedRows($day));
        }

        if ($type === 'all' || $type === 'staff') {
            $items = $items->concat($this->staffFeedRows($day));
        }

        $sorted = $items
            ->sortByDesc(fn (array $row): string => ($row['sort_time'] ?? '').'-'.$row['kind'].'-'.$row['name'])
            ->values();

        $total = $sorted->count();
        $slice = $sorted->forPage($page, $perPage)->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function studentFeedRows(string $day): Collection
    {
        $records = Attendance::query()
            ->whereDate('attendance_date', $day)
            ->with(['student.activeEnrollment', 'batch.course', 'markedBy'])
            ->orderByDesc('checked_in_at')
            ->orderBy('id')
            ->get();

        $waByStudent = $this->whatsappStatusByStudent($day, $records->pluck('student_id')->filter()->all());

        return $records->map(function (Attendance $row) use ($waByStudent): array {
            $status = $row->status instanceof AttendanceStatus
                ? $row->status
                : AttendanceStatus::tryFrom((string) $row->status);

            $source = AttendanceSourceLabel::for(
                $row->punch_source,
                $row->markedBy?->name,
            );
            $channel = AttendanceSourceLabel::isManual($row->punch_source) ? 'Manual' : 'Auto / machine';

            $detail = ClassSectionLabel::forBatch($row->batch, includeSession: false, includeShift: false)
                .($row->student?->activeEnrollment?->enrollment_number
                    ? ' · Roll '.$row->student->activeEnrollment->enrollment_number
                    : '');
            if ($status === AttendanceStatus::Leave && filled($row->leave_reason)) {
                $detail .= ' · '.$row->leave_reason;
            }

            return [
                'kind' => 'student',
                'kind_label' => 'Student',
                'name' => $row->student?->name ?? '—',
                'detail' => $detail,
                'status' => $status?->label() ?? '—',
                'status_value' => $status?->value ?? '',
                'leave_reason' => $status === AttendanceStatus::Leave ? ($row->leave_reason ?: null) : null,
                'channel' => $channel,
                'source' => $source,
                'in_at' => $row->checked_in_at?->format('h:i A'),
                'out_at' => $row->checked_out_at?->format('h:i A'),
                'whatsapp' => $waByStudent[(int) $row->student_id] ?? 'Not sent',
                'sort_time' => $row->checked_in_at?->format('Y-m-d H:i:s')
                    ?? $row->attendance_date?->format('Y-m-d').' 00:00:00',
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function staffFeedRows(string $day): Collection
    {
        $records = StaffAttendance::query()
            ->whereDate('attendance_date', $day)
            ->with(['user.staffProfile', 'markedBy'])
            ->orderByDesc('checked_in_at')
            ->orderBy('id')
            ->get();

        return $records->map(function (StaffAttendance $row): array {
            $status = $row->status instanceof AttendanceStatus
                ? $row->status
                : AttendanceStatus::tryFrom((string) $row->status);

            $source = AttendanceSourceLabel::for(
                $row->punch_source,
                $row->markedBy?->name,
            );
            $channel = AttendanceSourceLabel::isManual($row->punch_source) ? 'Manual' : 'Auto / machine';

            return [
                'kind' => 'staff',
                'kind_label' => 'Staff',
                'name' => $row->user?->name ?? '—',
                'detail' => $row->user?->staffProfile?->employee_code
                    ? 'Staff ID '.$row->user->staffProfile->employee_code
                    : ($row->user?->staffProfile?->designation ?? 'Staff'),
                'status' => $status?->label() ?? '—',
                'status_value' => $status?->value ?? '',
                'channel' => $channel,
                'source' => $source,
                'in_at' => $row->checked_in_at?->format('h:i A'),
                'out_at' => $row->checked_out_at?->format('h:i A'),
                'whatsapp' => '—',
                'sort_time' => $row->checked_in_at?->format('Y-m-d H:i:s')
                    ?? $row->attendance_date?->format('Y-m-d').' 00:00:00',
            ];
        });
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, string>
     */
    protected function whatsappStatusByStudent(string $day, array $studentIds): array
    {
        if ($studentIds === [] || ! Schema::hasTable('attendance_punch_whatsapp_logs')) {
            return [];
        }

        $logs = AttendancePunchWhatsappLog::query()
            ->whereDate('punch_date', $day)
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('id')
            ->get();

        $map = [];

        foreach ($logs as $log) {
            $id = (int) $log->student_id;
            if (isset($map[$id])) {
                continue;
            }

            $status = strtolower((string) $log->status);
            $map[$id] = match (true) {
                in_array($status, ['sent', 'delivered', 'read', 'success'], true) => 'Sent',
                in_array($status, ['failed', 'error'], true) => 'Failed',
                in_array($status, ['queued', 'pending'], true) => 'Pending',
                default => filled($log->sent_at) ? 'Sent' : ucfirst($status ?: 'Logged'),
            };
        }

        return $map;
    }
}
