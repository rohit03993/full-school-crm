<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\WhatsAppMessageSource;
use App\Models\Student;
use App\Models\StudentConductRecord;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Support\Collection;

class StudentConductParentNoticeService
{
    public function __construct(
        protected MetaWhatsAppInboxService $inbox,
    ) {}

    /**
     * @param  Collection<int, StudentConductRecord>  $records
     * @return array{sent: int, skipped: int, note: string}
     */
    public function notifyMany(Collection $records, User $staff): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return [
                'sent' => 0,
                'skipped' => $records->count(),
                'note' => 'Reports are saved. WhatsApp is off, so parents were not messaged.',
            ];
        }

        $sent = 0;
        $skipped = 0;
        $firstError = null;

        foreach ($records as $record) {
            $student = $record->student;

            if (! $student instanceof Student) {
                $skipped++;

                continue;
            }

            $result = $this->notify($student, $record, $staff);

            if (($result['status'] ?? '') === 'success') {
                $sent++;

                continue;
            }

            $skipped++;
            $firstError ??= $result['error'] ?? null;
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'note' => $this->note($sent, $skipped, $firstError),
        ];
    }

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function notify(Student $student, StudentConductRecord $record, User $staff): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp is off, so parents were not messaged.'];
        }

        try {
            return $this->inbox->sendReply(
                $student,
                $this->message($student, $record, $staff),
                $staff,
                WhatsAppMessageSource::Profile,
            );
        } catch (\Throwable) {
            return ['status' => 'failed', 'error' => 'WhatsApp could not be sent.'];
        }
    }

    public function message(Student $student, StudentConductRecord $record, User $staff): string
    {
        $fine = $record->fine_charged
            ? 'Fine: ₹'.number_format((int) $record->amount)
            : 'Fine: none';

        return implode("\n", [
            'Dear Parent,',
            $student->name.' has a new school report.',
            'Type: '.$record->typeLabelList(),
            'Date: '.($record->recorded_on?->format('d M Y') ?? ''),
            $fine,
            'Note: '.$record->note,
            'Reported by: '.($staff->name ?: 'School'),
        ]);
    }

    protected function note(int $sent, int $skipped, ?string $firstError): string
    {
        if ($sent === 0) {
            return $firstError ?: 'Reports are saved. Parents were not messaged.';
        }

        $sentLine = 'WhatsApp sent to '.$sent.($sent === 1 ? ' parent.' : ' parents.');

        if ($skipped === 0) {
            return $sentLine;
        }

        return $sentLine.' '.$skipped.' not sent.';
    }
}
