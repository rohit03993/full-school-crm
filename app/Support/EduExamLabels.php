<?php

namespace App\Support;

/**
 * Education-friendly labels for exams, tests, and marks.
 */
class EduExamLabels
{
    public static function examType(): string
    {
        return 'Exam type';
    }

    public static function examTypes(): string
    {
        return CrmMenuLabels::examTypes();
    }

    public static function test(): string
    {
        return 'Exam';
    }

    public static function tests(): string
    {
        return CrmMenuLabels::examResults();
    }

    public static function createExam(): string
    {
        return CrmMenuLabels::createExam();
    }

    public static function enterMarks(): string
    {
        return 'Enter marks';
    }

    public static function scheduleTest(): string
    {
        return 'Add one subject';
    }

    public static function markAttendancePageTitle(): string
    {
        return 'Enter marks';
    }
}
