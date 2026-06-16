<?php

namespace App\Http\Controllers\StudentPortal\Concerns;

use App\Models\Student;

trait ResolvesPortalStudent
{
    protected function portalStudent(): Student
    {
        return Student::query()
            ->with(['activeEnrollment.course', 'activeEnrollment.feeStructure'])
            ->findOrFail(session('student_portal_id'));
    }
}
