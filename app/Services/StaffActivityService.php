<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppSendActor;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\FeeStructureHistory;
use App\Models\HomeworkAssignment;
use App\Models\MetaWhatsAppMessage;
use App\Models\ParentFeeNotice;
use App\Models\Payment;
use App\Models\StaffAttendance;
use App\Models\StudentCall;
use App\Models\StudentCase;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppCampaign;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StaffActivityService
{
    public function canView(?User $viewer, User $subject): bool
    {
        if (! $viewer?->is_active) {
            return false;
        }

        if ($viewer->id === $subject->id) {
            return CrmAccess::hasPanelAccess($viewer);
        }

        return CrmAccess::can($viewer, CrmPermission::StaffManage);
    }

    /**
     * @return list<array{key: string, label: string, value: string, meta: string, url: string}>
     */
    public function tiles(User $subject, StaffActivityRange $range): array
    {
        $tiles = [];

        foreach (StaffActivityType::cases() as $type) {
            if (! $this->visible($subject, $type)) {
                continue;
            }

            $summary = $this->summary($subject, $type, $range);
            $tiles[] = [
                'key' => $type->value,
                'label' => $type->label(),
                'value' => $summary['value'],
                'meta' => $summary['meta'],
            ];
        }

        return $tiles;
    }

    /**
     * @return LengthAwarePaginator<int, array{when: string, title: string, detail: string, url: ?string}>
     */
    public function rows(User $viewer, User $subject, StaffActivityType $type, StaffActivityRange $range): LengthAwarePaginator
    {
        if ($type === StaffActivityType::AttendanceMarked) {
            $items = $this->query($subject, $type, $range)->reorder()->get();
            $page = max(1, (int) request()->integer('page', 1));
            $slice = $items->forPage($page, 15)->values();

            return new \Illuminate\Pagination\LengthAwarePaginator(
                $slice->map(fn (mixed $row): array => $this->present($viewer, $type, $row)),
                $items->count(),
                15,
                $page,
                ['path' => request()->url(), 'query' => request()->query()],
            );
        }

        $page = $this->query($subject, $type, $range)
            ->paginate(15)
            ->withQueryString();

        return $page->through(fn (mixed $row): array => $this->present($viewer, $type, $row));
    }

    public function visible(User $subject, StaffActivityType $type): bool
    {
        return match ($type) {
            StaffActivityType::Attendance => true,
            StaffActivityType::Calls => $this->allowed($subject, LicenseFeature::Calls, CrmPermission::LeadsCall),
            StaffActivityType::InboxMessages => $this->allowed($subject, LicenseFeature::WhatsApp, CrmPermission::WhatsappInbox),
            StaffActivityType::Campaigns => $this->allowed($subject, LicenseFeature::WhatsApp, CrmPermission::WhatsappCampaigns),
            StaffActivityType::FeeNotices => $this->allowed($subject, LicenseFeature::WhatsApp, CrmPermission::WhatsappFeeNotices),
            StaffActivityType::Homework => $this->allowed($subject, LicenseFeature::Homework, CrmPermission::HomeworkManage),
            StaffActivityType::HomeworkMessages => $this->allowed($subject, LicenseFeature::Homework, CrmPermission::HomeworkManage)
                && FeatureGate::enabled(LicenseFeature::WhatsApp),
            StaffActivityType::AttendanceMarked => $this->allowed($subject, LicenseFeature::Attendance, CrmPermission::AttendanceMark),
            StaffActivityType::FeesCollected => $this->allowed($subject, LicenseFeature::Fees, CrmPermission::FeesCollect),
            StaffActivityType::FeeChanges => $this->allowed($subject, LicenseFeature::Fees, CrmPermission::FeesAdjustStructure),
            StaffActivityType::AdmissionsApproved => $this->allowed($subject, LicenseFeature::Admissions, CrmPermission::AdmissionsApprove),
            StaffActivityType::Certificates => $this->allowed($subject, LicenseFeature::Certificates, CrmPermission::CertificatesIssue),
            StaffActivityType::CasesOpened => $this->allowed($subject, LicenseFeature::Cases, CrmPermission::CasesOpen),
            StaffActivityType::Visits => FeatureGate::enabled(LicenseFeature::Enquiries)
                && CrmAccess::canAny($subject, CrmPermission::VisitsViewAll, CrmPermission::LeadsCall),
        };
    }

    /**
     * @return array{value: string, meta: string}
     */
    public function summary(User $subject, StaffActivityType $type, StaffActivityRange $range): array
    {
        if ($type === StaffActivityType::FeesCollected) {
            $query = $this->query($subject, $type, $range);
            $amount = (float) (clone $query)->sum('amount');
            $count = (clone $query)->count();

            return [
                'value' => '₹'.number_format($amount, 0),
                'meta' => $count.' receipt'.($count === 1 ? '' : 's'),
            ];
        }

        if ($type === StaffActivityType::InboxMessages || $type === StaffActivityType::HomeworkMessages) {
            $query = $this->query($subject, $type, $range);
            $count = (clone $query)->count();
            $students = (clone $query)->whereNotNull('student_id')->distinct('student_id')->count('student_id');

            return [
                'value' => (string) $count,
                'meta' => $students.' student'.($students === 1 ? '' : 's'),
            ];
        }

        if ($type === StaffActivityType::AttendanceMarked) {
            $count = (clone $this->query($subject, $type, $range))->reorder()->get()->count();

            return [
                'value' => (string) $count,
                'meta' => $range->label(),
            ];
        }

        return [
            'value' => (string) $this->query($subject, $type, $range)->count(),
            'meta' => $range->label(),
        ];
    }

    public function query(User $subject, StaffActivityType $type, StaffActivityRange $range): Builder
    {
        [$start, $end] = $range->bounds();

        return match ($type) {
            StaffActivityType::Attendance => StaffAttendance::query()
                ->where('user_id', $subject->id)
                ->whereDate('attendance_date', '>=', $start->toDateString())
                ->whereDate('attendance_date', '<=', $end->toDateString())
                ->orderByDesc('attendance_date'),
            StaffActivityType::Calls => StudentCall::query()
                ->with('student')
                ->where('user_id', $subject->id)
                ->whereBetween('called_at', [$start, $end])
                ->orderByDesc('called_at'),
            StaffActivityType::InboxMessages => $this->staffMessages($subject, $start, $end, [
                WhatsAppMessageSource::Inbox->value,
                WhatsAppMessageSource::Profile->value,
            ]),
            StaffActivityType::Campaigns => WhatsAppCampaign::query()
                ->where('created_by', $subject->id)
                ->where(function (Builder $query) use ($start, $end): void {
                    $query->whereBetween('shot_at', [$start, $end])
                        ->orWhere(function (Builder $inner) use ($start, $end): void {
                            $inner->whereNull('shot_at')->whereBetween('created_at', [$start, $end]);
                        });
                })
                ->orderByDesc('shot_at')
                ->orderByDesc('id'),
            StaffActivityType::FeeNotices => ParentFeeNotice::query()
                ->with('student')
                ->where('sent_by_user_id', $subject->id)
                ->whereBetween('sent_at', [$start, $end])
                ->orderByDesc('sent_at'),
            StaffActivityType::Homework => HomeworkAssignment::query()
                ->with('batch')
                ->where('created_by_user_id', $subject->id)
                ->whereBetween('created_at', [$start, $end])
                ->orderByDesc('created_at'),
            StaffActivityType::HomeworkMessages => $this->staffMessages($subject, $start, $end, [
                WhatsAppMessageSource::Homework->value,
            ]),
            StaffActivityType::AttendanceMarked => Attendance::query()
                ->leftJoin('batches', 'batches.id', '=', 'attendances.batch_id')
                ->select([
                    'attendances.batch_id',
                    'attendances.attendance_date',
                    'batches.name as batch_name',
                    DB::raw('COUNT(*) as marked_count'),
                    DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present_count"),
                ])
                ->where('attendances.marked_by_user_id', $subject->id)
                ->whereIn('attendances.punch_source', ['manual', 'roll_call'])
                ->whereDate('attendances.attendance_date', '>=', $start->toDateString())
                ->whereDate('attendances.attendance_date', '<=', $end->toDateString())
                ->groupBy('attendances.batch_id', 'attendances.attendance_date', 'batches.name')
                ->orderByDesc('attendances.attendance_date'),
            StaffActivityType::FeesCollected => Payment::query()
                ->with('student')
                ->active()
                ->where('added_by_user_id', $subject->id)
                ->whereDate('payment_date', '>=', $start->toDateString())
                ->whereDate('payment_date', '<=', $end->toDateString())
                ->orderByDesc('payment_date')
                ->orderByDesc('id'),
            StaffActivityType::FeeChanges => FeeStructureHistory::query()
                ->with('feeStructure.enrollment.student')
                ->where('changed_by_user_id', $subject->id)
                ->whereBetween('changed_at', [$start, $end])
                ->orderByDesc('changed_at'),
            StaffActivityType::AdmissionsApproved => Admission::query()
                ->with('student')
                ->where('approved_by_user_id', $subject->id)
                ->whereBetween('approved_at', [$start, $end])
                ->orderByDesc('approved_at'),
            StaffActivityType::Certificates => StudentCertificate::query()
                ->with('student')
                ->where('issued_by_user_id', $subject->id)
                ->whereDate('issued_on', '>=', $start->toDateString())
                ->whereDate('issued_on', '<=', $end->toDateString())
                ->orderByDesc('issued_on'),
            StaffActivityType::CasesOpened => StudentCase::query()
                ->with('student')
                ->where('opened_by_user_id', $subject->id)
                ->whereBetween('opened_at', [$start, $end])
                ->orderByDesc('opened_at'),
            StaffActivityType::Visits => Visit::query()
                ->with('student')
                ->where('staff_user_id', $subject->id)
                ->where(function (Builder $query): void {
                    $query->whereNull('remarks')
                        ->orWhereNotIn('remarks', Visit::PHONE_CALL_REMARKS);
                })
                ->whereDate('visit_date', '>=', $start->toDateString())
                ->whereDate('visit_date', '<=', $end->toDateString())
                ->orderByDesc('visit_date'),
        };
    }

    /**
     * @param  list<string>  $sources
     */
    protected function staffMessages(User $subject, Carbon $start, Carbon $end, array $sources): Builder
    {
        return MetaWhatsAppMessage::query()
            ->with('student')
            ->where('sent_by_user_id', $subject->id)
            ->where('direction', MetaWhatsAppMessageDirection::Outbound->value)
            ->where('send_actor', WhatsAppSendActor::Staff->value)
            ->whereIn('message_source', $sources)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at');
    }

    protected function allowed(User $subject, LicenseFeature $feature, CrmPermission $permission): bool
    {
        return FeatureGate::enabled($feature) && CrmAccess::can($subject, $permission);
    }

    /**
     * @return array{when: string, title: string, detail: string, url: ?string}
     */
    protected function present(User $viewer, StaffActivityType $type, mixed $row): array
    {
        return match ($type) {
            StaffActivityType::Attendance => [
                'when' => $row->attendance_date?->format('d M Y') ?? '—',
                'title' => $row->status?->label() ?? 'Marked',
                'detail' => collect([
                    $row->checked_in_at ? 'In '.$row->checked_in_at->format('h:i A') : null,
                    $row->checked_out_at ? 'Out '.$row->checked_out_at->format('h:i A') : null,
                ])->filter()->implode(' · ') ?: 'No punch time',
                'url' => null,
            ],
            StaffActivityType::Calls => [
                'when' => $row->called_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => collect([
                    $row->call_status?->label(),
                    $row->call_purpose?->label(),
                ])->filter()->implode(' · ') ?: 'Call logged',
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::InboxMessages, StaffActivityType::HomeworkMessages => [
                'when' => $row->created_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->student?->name ?? $this->contactLabel($viewer, $row->phone),
                'detail' => $row->template_name ?: 'WhatsApp message',
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::Campaigns => [
                'when' => ($row->shot_at ?? $row->created_at)?->format('d M Y, h:i A') ?? '—',
                'title' => $row->name ?: 'Campaign',
                'detail' => ((int) $row->sent_count).' sent · '.($row->status?->label() ?? 'Campaign'),
                'url' => null,
            ],
            StaffActivityType::FeeNotices => [
                'when' => $row->sent_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => '₹'.number_format((float) $row->amount, 0),
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::Homework => [
                'when' => $row->created_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->title ?: 'Homework',
                'detail' => collect([
                    $row->batch?->name,
                    $row->whatsapp_sent_count ? ((int) $row->whatsapp_sent_count).' WhatsApp sent' : null,
                ])->filter()->implode(' · ') ?: 'Assigned',
                'url' => null,
            ],
            StaffActivityType::AttendanceMarked => [
                'when' => $row->attendance_date ? Carbon::parse($row->attendance_date)->format('d M Y') : '—',
                'title' => $row->batch_name ?? 'Class',
                'detail' => ((int) $row->present_count).' present · '.((int) $row->marked_count).' marked',
                'url' => null,
            ],
            StaffActivityType::FeesCollected => [
                'when' => $row->payment_date?->format('d M Y') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => '₹'.number_format((float) $row->amount, 0).($row->receipt_number ? ' · '.$row->receipt_number : ''),
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::FeeChanges => [
                'when' => $row->changed_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->feeStructure?->enrollment?->student?->name ?? 'Fee plan',
                'detail' => 'Net ₹'.number_format((float) $row->new_net_fee, 0),
                'url' => $this->profileUrl($row->feeStructure?->enrollment?->student_id),
            ],
            StaffActivityType::AdmissionsApproved => [
                'when' => $row->approved_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => $row->admission_number ?: 'Approved',
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::Certificates => [
                'when' => $row->issued_on?->format('d M Y') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => $row->type?->label() ?? 'Certificate',
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::CasesOpened => [
                'when' => $row->opened_at?->format('d M Y, h:i A') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => $row->title ?: ($row->case_number ?: 'Case opened'),
                'url' => $this->profileUrl($row->student_id),
            ],
            StaffActivityType::Visits => [
                'when' => $row->visit_date?->format('d M Y') ?? '—',
                'title' => $row->student?->name ?? 'Student',
                'detail' => $row->campus_purpose?->label() ?? ($row->status?->label() ?? 'Visit'),
                'url' => $this->profileUrl($row->student_id),
            ],
        };
    }

    protected function profileUrl(mixed $studentId): ?string
    {
        if (! filled($studentId)) {
            return null;
        }

        return StudentProfilePage::getUrl(['record' => (int) $studentId]);
    }

    protected function contactLabel(User $viewer, ?string $phone): string
    {
        if (! filled($phone)) {
            return 'Unknown contact';
        }

        return CrmAccess::canViewStudentMobile($viewer) ? (string) $phone : 'Hidden';
    }
}
