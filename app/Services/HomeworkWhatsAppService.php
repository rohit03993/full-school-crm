<?php

namespace App\Services;

use App\Models\HomeworkAssignment;
use App\Models\Setting;
use App\Models\Student;

class HomeworkWhatsAppService
{
    public function __construct(
        protected PalDigitalWhatsAppService $whatsapp,
        protected HomeworkAssignmentService $homework,
    ) {}

    /**
     * @return array{sent: int, failed: int}
     */
    public function notifyBatch(HomeworkAssignment $assignment, ?string $templateName = null): array
    {
        if (! $this->whatsapp->isConfigured()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $templateName ??= (string) Setting::getValue(
            'whatsapp.homework_template_name',
            config('whatsapp.homework_template_name', 'homework_api'),
        );

        if (blank($templateName)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $link = $assignment->portalUrl();
        $students = $this->homework->batchStudentsWithMobile($assignment->batch_id);

        $sent = 0;
        $failed = 0;

        foreach ($students as $student) {
            $result = $this->notifyStudent($student, $assignment, $templateName, $link);

            if ($result === 'sent') {
                $sent++;
            } elseif ($result === 'failed') {
                $failed++;
            }
        }

        return compact('sent', 'failed');
    }

    /**
     * @return 'sent'|'failed'|'skipped'
     */
    public function notifyStudent(
        Student $student,
        HomeworkAssignment $assignment,
        ?string $templateName = null,
        ?string $link = null,
    ): string {
        if (! $this->whatsapp->isConfigured()) {
            return 'skipped';
        }

        $templateName ??= (string) Setting::getValue(
            'whatsapp.homework_template_name',
            config('whatsapp.homework_template_name', 'homework_api'),
        );

        if (blank($templateName) || blank($student->mobile)) {
            return 'skipped';
        }

        $link ??= $assignment->portalUrl();
        $roll = (string) ($student->activeEnrollment?->enrollment_number ?? '');

        $params = [
            (string) ($student->name ?? 'Student'),
            $roll !== '' ? $roll : '—',
            (string) $assignment->title,
            $link,
        ];

        $result = $this->whatsapp->send(
            (string) $student->mobile,
            $params,
            $templateName,
            (string) ($student->name ?? 'Student'),
            4,
        );

        return $result['status'] === 'success' ? 'sent' : 'failed';
    }
}
