<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\View\View;

class IdCardVerifyController extends Controller
{
    public function __invoke(string $enrollment): View
    {
        $record = Enrollment::query()
            ->with(['student', 'course'])
            ->where('enrollment_number', $enrollment)
            ->where('is_active', true)
            ->firstOrFail();

        return view('public.id-card-verify', [
            'enrollment' => $record,
            'student' => $record->student,
            'course' => $record->course,
        ]);
    }
}
