<?php

namespace App\Enums;

enum StaffActivityType: string
{
    case Attendance = 'attendance';
    case Calls = 'calls';
    case InboxMessages = 'inbox_messages';
    case Campaigns = 'campaigns';
    case FeeNotices = 'fee_notices';
    case Homework = 'homework';
    case HomeworkMessages = 'homework_messages';
    case AttendanceMarked = 'attendance_marked';
    case FeesCollected = 'fees_collected';
    case FeeChanges = 'fee_changes';
    case AdmissionsApproved = 'admissions_approved';
    case Certificates = 'certificates';
    case CasesOpened = 'cases_opened';
    case Visits = 'visits';

    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'My attendance',
            self::Calls => 'Calls logged',
            self::InboxMessages => 'Inbox messages sent',
            self::Campaigns => 'Campaigns sent',
            self::FeeNotices => 'Fee notices sent',
            self::Homework => 'Homework given',
            self::HomeworkMessages => 'Homework messages sent',
            self::AttendanceMarked => 'Classes marked',
            self::FeesCollected => 'Fees collected',
            self::FeeChanges => 'Fee changes',
            self::AdmissionsApproved => 'Admissions approved',
            self::Certificates => 'Certificates issued',
            self::CasesOpened => 'Cases opened',
            self::Visits => 'Visits logged',
        };
    }
}
