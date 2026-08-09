<?php

namespace App\Enums;

enum AskCrmIntent: string
{
    case Help = 'help';
    case AttendanceToday = 'attendance_today';
    case AttendanceMonth = 'attendance_month';
    case FeePending = 'fee_pending';
    case HomeworkWeek = 'homework_week';
    case Unknown = 'unknown';
}
