<?php

namespace App\Support;

class WhatsAppTemplateParamMappingInferrer
{
    /**
     * Map normalized Pal Digital / Meta body variable labels to CRM data sources.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'student_name' => 'student.name',
        'studentname' => 'student.name',
        'name' => 'student.name',
        'student' => 'student.name',
        'ward_name' => 'student.name',
        'ward' => 'student.name',
        'father_name' => 'student.father_name',
        'fathername' => 'student.father_name',
        'parent_name' => 'student.father_name',
        'parent' => 'student.father_name',
        'mobile' => 'student.mobile',
        'student_mobile' => 'student.mobile',
        'phone' => 'student.mobile',
        'student_phone' => 'student.mobile',
        'contact_number' => 'student.mobile',
        'roll_number' => 'student.enrollment_number',
        'roll_numbebr' => 'student.enrollment_number',
        'roll_no' => 'student.enrollment_number',
        'rollnumber' => 'student.enrollment_number',
        'roll' => 'student.enrollment_number',
        'enrollment_number' => 'student.enrollment_number',
        'enrollment_no' => 'student.enrollment_number',
        'id_roll_number' => 'student.enrollment_number',
        'batch' => 'batch.name',
        'batch_name' => 'batch.name',
        'class' => 'batch.name',
        'class_name' => 'batch.name',
        'section' => 'batch.name',
        'course' => 'enrollment.course.name',
        'course_name' => 'enrollment.course.name',
        'institute_name' => 'institute.name',
        'institute' => 'institute.name',
        'school_name' => 'institute.name',
        'school' => 'institute.name',
        'institute_phone' => 'institute.phone',
        'school_phone' => 'institute.phone',
        'caller_name' => 'caller.name',
        'staff_name' => 'caller.name',
        'caller_mobile' => 'caller.mobile',
        'staff_mobile' => 'caller.mobile',
        'date' => 'campaign.date',
        'announcement_date' => 'campaign.date',
        'check_out_date' => 'campaign.date',
        'checkout_date' => 'campaign.date',
        'check_in_date' => 'campaign.date',
        'checkin_date' => 'campaign.date',
        'time' => 'campaign.time',
        'check_out_time' => 'campaign.time',
        'checkout_time' => 'campaign.time',
        'check_in_time' => 'campaign.time',
        'checkin_time' => 'campaign.time',
        'topic' => 'campaign.topic',
        'announcement_topic' => 'campaign.topic',
        'subject' => 'campaign.subject',
        'announcement_subject' => 'campaign.subject',
        'tes' => 'activity.test_name',
        'test' => 'activity.test_name',
        'test_name' => 'activity.test_name',
        'exam_name' => 'activity.test_name',
        'exam' => 'activity.test_name',
        'test_date' => 'activity.test_date',
        'exam_date' => 'activity.test_date',
        'all_subject_marks' => 'activity.marks_summary',
        'subject_marks' => 'activity.marks_summary',
        'marks_summary' => 'activity.marks_summary',
        'all_marks' => 'activity.marks_summary',
        'marks' => 'activity.marks_summary',
        'homework_title' => 'homework.title',
        'homework_link' => 'homework.portal_link',
        'portal_link' => 'homework.portal_link',
        'link' => 'homework.portal_link',
    ];

    /**
     * @param  list<mixed>  $bodyVariables
     * @return list<string|null>
     */
    public static function infer(array $bodyVariables, int $paramCount, ?string $templateName = null): array
    {
        $bodyVariables = array_values($bodyVariables);

        if ($paramCount < 1) {
            return [];
        }

        if (self::looksPositionalOnly($bodyVariables)) {
            if ($templateName && self::looksLikeMarksTemplateName($templateName)) {
                return self::marksDefaults($paramCount);
            }

            return self::attendancePunchDefaults($paramCount);
        }

        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $label = isset($bodyVariables[$i]) ? (string) $bodyVariables[$i] : '';
            $sources[] = self::inferSource($label);
        }

        return $sources;
    }

    /**
     * @return list<string|null>
     */
    public static function marksDefaults(int $paramCount): array
    {
        $defaults = [
            0 => 'student.name',
            1 => 'student.enrollment_number',
            2 => 'activity.test_name',
            3 => 'activity.marks_summary',
        ];

        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $sources[] = $defaults[$i] ?? null;
        }

        return $sources;
    }

    public static function looksLikeMarksTemplateName(string $name): bool
    {
        $normalized = strtolower(trim($name));

        foreach (['marks', 'test_marks', 'activity_marks', 'exam_marks'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string|null>  $sources
     * @return list<string|null>
     */
    public static function fillAttendancePunchDefaults(array $sources, int $paramCount): array
    {
        $defaults = self::attendancePunchDefaults($paramCount);

        for ($i = 0; $i < $paramCount; $i++) {
            if (! filled($sources[$i] ?? null) && filled($defaults[$i] ?? null)) {
                $sources[$i] = $defaults[$i];
            }
        }

        return $sources;
    }

    /**
     * @param  list<mixed>  $bodyVariables
     */
    public static function looksPositionalOnly(array $bodyVariables): bool
    {
        if ($bodyVariables === []) {
            return false;
        }

        foreach ($bodyVariables as $variable) {
            if (preg_match('/^\d+$/', trim((string) $variable)) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Standard punch / attendance template slots used across Folks and Meta templates.
     *
     * @return list<string|null>
     */
    public static function attendancePunchDefaults(int $paramCount): array
    {
        $defaults = [
            0 => 'student.name',
            1 => 'student.enrollment_number',
            2 => 'campaign.time',
            3 => 'campaign.date',
            4 => 'attendance.status',
        ];

        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $sources[] = $defaults[$i] ?? null;
        }

        return $sources;
    }

    public static function inferSource(?string $variableLabel): ?string
    {
        if (blank($variableLabel)) {
            return null;
        }

        $key = self::normalize($variableLabel);

        if ($key === '' || preg_match('/^\d+$/', $key) === 1) {
            return null;
        }

        return self::ALIASES[$key] ?? null;
    }

    public static function normalize(string $variableLabel): string
    {
        $key = strtolower(trim($variableLabel));
        $key = preg_replace('/[\s\-]+/', '_', $key) ?? $key;
        $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? $key;

        return trim($key, '_');
    }
}
