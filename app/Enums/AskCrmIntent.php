<?php

namespace App\Enums;

enum AskCrmIntent: string
{
    case Help = 'help';
    case MyTasks = 'my_tasks';
    case HowTo = 'how_to';
    case BatchStatus = 'batch_status';
    case AttendanceToday = 'attendance_today';
    case AttendanceMonth = 'attendance_month';
    case FeePending = 'fee_pending';
    case HomeworkWeek = 'homework_week';
    case StudentProfile = 'student_profile';
    case Unknown = 'unknown';
}
