<?php

namespace App\Services;

use App\Enums\AskCrmIntent;
use App\Enums\LicenseFeature;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\InstituteSettings;
use Carbon\Carbon;

class AskCrmStaffAssistService
{
    public function enhance(
        User $user,
        Student $student,
        AskCrmIntent $intent,
        string $reply,
        array $snapshot,
        string $question,
        ?string $referencedDate = null,
    ): string {
        if ($this->wantsParentWhatsAppCopy($question)) {
            $reply .= "\n\n".$this->parentWhatsAppBlock($user, $student, $intent, $snapshot, $referencedDate);
        }

        return $this->appendQuickLinks($reply, $user, $student, $intent);
    }

    public function wantsParentWhatsAppCopy(string $question): bool
    {
        $q = ' '.mb_strtolower(trim($question)).' ';

        foreach ([
            ' whatsapp message', ' message for parent', ' parent message', ' parent ko',
            ' whatsapp copy', ' whatsapp msg', ' send to parent', ' for parent',
            ' parent ke liye', ' copy for parent', ' whatsapp for parent',
            ' msg for parent', ' parent whatsapp',
        ] as $needle) {
            if (str_contains($q, $needle)) {
                return true;
            }
        }

        return (bool) preg_match('/\bwhatsapp\b.*\bparent\b/u', trim($question))
            || (bool) preg_match('/\bparent\b.*\bwhatsapp\b/u', trim($question));
    }

    public function appendQuickLinks(string $reply, User $user, Student $student, AskCrmIntent $intent): string
    {
        $links = [
            '[Open profile]('.$this->profileUrl((int) $student->id).')',
        ];

        if ($this->canLinkTab($user, 'homework') && in_array($intent, [AskCrmIntent::HomeworkWeek, AskCrmIntent::StudentProfile], true)) {
            $links[] = '[Homework]('.$this->profileUrl((int) $student->id, 'homework').')';
        }

        if ($this->canLinkTab($user, 'fees') && in_array($intent, [AskCrmIntent::FeePending, AskCrmIntent::StudentProfile], true)) {
            $links[] = '[Fees]('.$this->profileUrl((int) $student->id, 'fees').')';
        }

        if ($this->canLinkTab($user, 'attendance') && in_array($intent, [AskCrmIntent::AttendanceToday, AskCrmIntent::AttendanceMonth, AskCrmIntent::StudentProfile], true)) {
            $links[] = '[Attendance]('.$this->profileUrl((int) $student->id, 'attendance').')';
        }

        if (count($links) === 1) {
            return $reply."\n\n---\n".$links[0];
        }

        return $reply."\n\n---\n".implode(' · ', $links);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function parentWhatsAppBlock(
        User $user,
        Student $student,
        AskCrmIntent $intent,
        array $snapshot,
        ?string $referencedDate = null,
    ): string {
        $copy = $this->parentWhatsAppCopy($user, $student, $intent, $snapshot, $referencedDate);

        return "📋 **Parent WhatsApp copy** (select text below and copy):\n".$copy;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function parentWhatsAppCopy(
        User $user,
        Student $student,
        AskCrmIntent $intent,
        array $snapshot,
        ?string $referencedDate = null,
    ): string {
        $institute = InstituteSettings::brandName();
        $studentName = $student->name;
        $batch = $student->activeBatchStudent?->batch?->name;
        $batchBit = filled($batch) ? ' ('.$batch.')' : '';

        $body = match ($intent) {
            AskCrmIntent::HomeworkWeek => $this->parentHomeworkCopy($snapshot, $referencedDate),
            AskCrmIntent::AttendanceToday, AskCrmIntent::AttendanceMonth => $this->parentAttendanceCopy($student, $intent, $snapshot, $referencedDate),
            AskCrmIntent::FeePending => $this->parentFeeCopy($user, $snapshot),
            default => 'Please contact the institute for an update regarding your ward.',
        };

        if ($body === null) {
            return 'Parent message is not available for this topic with your current permissions.';
        }

        return 'Dear Parent, This is regarding '.$studentName.$batchBit.'. '.$body.' — '.$institute;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function parentHomeworkCopy(array $snapshot, ?string $referencedDate): ?string
    {
        $homework = $snapshot['homework'] ?? [];

        if (! ($homework['enabled'] ?? false)) {
            return null;
        }

        if (filled($referencedDate)) {
            $onDate = $homework['on_referenced_date'] ?? null;

            if (! is_array($onDate) || ! ($onDate['marked'] ?? false)) {
                return 'Homework for '.Carbon::parse($referencedDate)->format('d M Y').' has not been checked yet.';
            }

            $notDone = (int) ($onDate['not_done_count'] ?? 0);
            $done = (int) ($onDate['done_count'] ?? 0);
            $formattedDate = Carbon::parse($referencedDate)->format('d M Y');

            if ($notDone > 0 && $done === 0) {
                return 'Homework for '.$formattedDate.' was marked Not Done. Please ensure your ward completes pending work.';
            }

            if ($done > 0 && $notDone === 0) {
                return 'Homework for '.$formattedDate.' was marked Done. Thank you for your support.';
            }

            return 'Homework for '.$formattedDate.' has mixed marks. Please check with the class teacher.';
        }

        $today = $homework['today'] ?? [];
        $recent = collect($homework['recent_checks'] ?? [])
            ->first(fn (array $check): bool => ($check['status'] ?? '') === 'Not Done');

        if (($today['not_done_count'] ?? 0) > 0) {
            return 'Homework for today was marked Not Done. Please ensure your ward completes it.';
        }

        if (is_array($recent)) {
            $date = filled($recent['date'] ?? null)
                ? Carbon::parse((string) $recent['date'])->format('d M Y')
                : 'a recent date';
            $subject = trim((string) ($recent['subject'] ?? 'homework'));

            return 'Latest homework check on '.$date.' ('.$subject.') was marked Not Done. Please follow up at home.';
        }

        return 'Homework is up to date based on our latest checks. Thank you for your support.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function parentAttendanceCopy(Student $student, AskCrmIntent $intent, array $snapshot, ?string $referencedDate): ?string
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return null;
        }

        if (filled($referencedDate)) {
            $record = $snapshot['attendance']['on_referenced_date']['record'] ?? null;
            $formattedDate = Carbon::parse($referencedDate)->format('d M Y');

            if (! is_array($record) || ($record['status'] ?? 'not_marked') === 'not_marked') {
                return 'Attendance for '.$formattedDate.' has not been marked yet.';
            }

            return 'Attendance on '.$formattedDate.' was marked as '.$record['status'].'.';
        }

        if ($intent === AskCrmIntent::AttendanceMonth) {
            $month = $snapshot['attendance']['month'] ?? null;

            if (! is_array($month) || ! isset($month['percentage'])) {
                return 'Monthly attendance details are not available yet.';
            }

            return 'Attendance this month is '.$month['percentage'].'% ('.($month['period_label'] ?? 'current month').').';
        }

        $today = $snapshot['attendance']['today'] ?? null;

        if (! is_array($today)) {
            return 'Today\'s attendance has not been marked yet.';
        }

        $status = (string) ($today['status'] ?? 'not_marked_yet');

        if ($status === 'not_marked_yet') {
            return 'Today\'s attendance has not been marked yet.';
        }

        return 'Today\'s attendance is marked as '.$status.'.';
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function parentFeeCopy(User $user, array $snapshot): ?string
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return null;
        }

        if (! CrmAccess::canViewFees($user)) {
            return null;
        }

        $fees = $snapshot['fees'] ?? [];

        if (! ($fees['can_view'] ?? false)) {
            return null;
        }

        $pending = (float) ($fees['tuition_pending'] ?? 0);

        if ($pending <= 0.009) {
            return 'Fee account is clear on tuition as per our records. Thank you for your timely payments.';
        }

        return 'Tuition fee pending is Rs '.number_format($pending, 2).'. Kindly clear the balance at your earliest convenience.';
    }

    protected function profileUrl(int $studentId, ?string $tab = null): string
    {
        $url = StudentProfilePage::getUrl(['record' => $studentId]);

        if (filled($tab)) {
            $url .= '?tab='.$tab;
        }

        return $url;
    }

    protected function canLinkTab(User $user, string $tab): bool
    {
        return match ($tab) {
            'homework' => FeatureGate::enabled(LicenseFeature::Homework),
            'fees' => FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user),
            'attendance' => FeatureGate::enabled(LicenseFeature::Attendance),
            default => true,
        };
    }
}
