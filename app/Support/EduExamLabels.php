<?php

namespace App\Support;

/**
 * Education-friendly labels for exams, tests, and marks (replaces generic "Activity" wording).
 */
class EduExamLabels
{
    public static function examType(): string
    {
        return 'Exam Type';
    }

    public static function examTypes(): string
    {
        return 'Exam Types';
    }

    public static function test(): string
    {
        return 'Test / Exam';
    }

    public static function tests(): string
    {
        return 'Tests & Exams';
    }

    public static function enterMarks(): string
    {
        return 'Enter Marks';
    }

    public static function scheduleTest(): string
    {
        return 'Schedule Test';
    }

    public static function markAttendancePageTitle(): string
    {
        return 'Enter Test Marks';
    }
}
