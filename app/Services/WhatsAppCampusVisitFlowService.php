<?php

namespace App\Services;

use App\Enums\CampusVisitPurpose;
use App\Enums\LicenseFeature;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppSendActor;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmCacheInvalidator;
use App\Support\FeatureGate;
use App\Support\MetaWhatsAppInboundMessageParser;
use App\Support\WhatsAppCampusVisitFlowJson;
use App\Support\WhatsAppInboxBodyPreview;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppCampusVisitFlowService
{
    public function __construct(
        protected WhatsAppFlowService $flows,
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppMessageLogger $logger,
        protected StudentWhatsAppThreadService $thread,
        protected WhatsAppProviderResolver $resolver,
    ) {}

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $message
     */
    public function handleInbound(string $phone, array $parsed, array $message, ?string $wamid, ?MetaWhatsAppMessage $record = null): void
    {
        try {
            $response = MetaWhatsAppInboundMessageParser::flowResponse($message);

            if ($response !== null) {
                $this->saveFinishedForm($phone, $response, $wamid, $record);

                return;
            }

            if (($parsed['message_type'] ?? '') !== 'text') {
                return;
            }

            if (! $this->isVisitTrigger((string) ($parsed['body_preview'] ?? ''))) {
                return;
            }

            $this->sendForm($phone, null, null, WhatsAppMessageSource::Automation);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp campus visit form failed', [
                'phone' => $phone,
                'wamid' => $wamid,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function sendForm(
        string $phone,
        ?User $sender = null,
        ?int $studentId = null,
        WhatsAppMessageSource $source = WhatsAppMessageSource::Inbox,
    ): array {
        if (! $this->resolver->isMetaActive()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not enabled. Open Connection & Setup and turn on WhatsApp.'];
        }

        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp module is not enabled.'];
        }

        $phone = $this->thread->normalizePhoneForStorage($phone);

        if ($phone === '') {
            return ['status' => 'failed', 'error' => 'No mobile number for this chat.'];
        }

        if (! $this->thread->sessionOpenForPhone($phone, $studentId)) {
            return [
                'status' => 'failed',
                'error' => 'The 24-hour reply window is closed. Ask them to message you first.',
            ];
        }

        $result = $this->flows->sendCampusVisit($phone);

        if (($result['status'] ?? '') === 'success') {
            $this->logger->recordOutboundText(
                $phone,
                $result['message_id'] ?? null,
                'Campus visit form',
                MetaWhatsAppMessageStatus::Sent,
                'Visit form',
                is_array($result['response'] ?? null) ? $result['response'] : null,
                $studentId,
                [
                    'message_source' => $source->value,
                    'sent_by_user_id' => $sender?->id,
                    'send_actor' => $sender ? WhatsAppSendActor::Staff->value : WhatsAppSendActor::Automatic->value,
                ],
            );
        }

        return $result;
    }

    public function isVisitTrigger(string $text): bool
    {
        $text = mb_strtolower(trim($text));
        $text = trim($text, " \t\n\r\0\x0B.!?");

        return $text === 'visit';
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function saveFinishedForm(string $phone, array $response, ?string $wamid, ?MetaWhatsAppMessage $record): void
    {
        $token = trim((string) ($response['flow_token'] ?? ''));

        if ($token !== '' && $token !== WhatsAppCampusVisitFlowJson::FLOW_TOKEN) {
            return;
        }

        if (filled($wamid) && Visit::query()->where('remarks', $this->visitMarker($wamid))->exists()) {
            return;
        }

        $summary = $this->summary($response);
        $student = $this->studentForPhone($phone);
        $enquiry = $student
            ? Enquiry::query()->where('student_id', $student->id)->latest('id')->first()
            : null;

        if ($record) {
            $record->update([
                'body_preview' => WhatsAppInboxBodyPreview::clip($summary),
            ]);
        }

        if ($student && $enquiry) {
            $visitDate = $this->visitDate($response);

            Visit::query()->create([
                'student_id' => $student->id,
                'enquiry_id' => $enquiry->id,
                'visit_date' => $visitDate->toDateString(),
                'next_follow_up_date' => $visitDate->toDateString(),
                'discussion_summary' => $summary,
                'remarks' => filled($wamid) ? $this->visitMarker((string) $wamid) : 'WhatsApp campus visit',
                'status' => VisitStatus::FollowUpRequired,
                'campus_purpose' => CampusVisitPurpose::General,
            ]);

            $enquiry->update([
                'latest_visit_status' => VisitStatus::FollowUpRequired,
            ]);

            CrmCacheInvalidator::afterVisitOrFollowUpChange($enquiry->meeting_with_user_id);
        }

        $this->sendConfirmation($phone, $student, $enquiry !== null);
    }

    protected function sendConfirmation(string $phone, ?Student $student, bool $savedOnEnquiry): void
    {
        if (! $this->resolver->isMetaActive()) {
            return;
        }

        $text = $savedOnEnquiry
            ? 'Thank you. Your campus visit is noted. Our team will confirm the date.'
            : 'Thank you. We received your campus visit form. Our team will call you.';

        $result = $this->meta->sendText($phone, $text);

        if (($result['status'] ?? '') !== 'success') {
            Log::warning('WhatsApp campus visit confirmation failed', [
                'phone' => $phone,
                'error' => $result['error'] ?? null,
            ]);

            return;
        }

        $this->logger->recordOutboundText(
            $phone,
            $result['message_id'] ?? null,
            $text,
            MetaWhatsAppMessageStatus::Sent,
            'Visit form confirmation',
            is_array($result['response'] ?? null) ? $result['response'] : null,
            $student?->id,
            [
                'message_source' => WhatsAppMessageSource::Automation->value,
                'send_actor' => WhatsAppSendActor::Automatic->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function summary(array $response): string
    {
        $course = $this->courseLabel($response);
        $date = $this->visitDate($response)->format('d M Y');
        $note = trim((string) ($response['note'] ?? ''));

        $lines = [
            'Campus visit booked on WhatsApp.',
            'Course: '.$course,
            'Date: '.$date,
        ];

        if ($note !== '') {
            $lines[] = 'Note: '.$note;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function courseLabel(array $response): string
    {
        $courseId = trim((string) ($response['course'] ?? ''));

        if ($courseId === '' || $courseId === 'general') {
            return 'General';
        }

        if (! ctype_digit($courseId)) {
            return $courseId;
        }

        $name = Course::query()->whereKey((int) $courseId)->value('name');

        return filled($name) ? (string) $name : 'Course '.$courseId;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    protected function visitDate(array $response): Carbon
    {
        $raw = trim((string) ($response['visit_date'] ?? ''));

        if ($raw === '') {
            return now()->startOfDay();
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    protected function studentForPhone(string $phone): ?Student
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $tenDigit = strlen($digits) === 12 && str_starts_with($digits, '91')
            ? substr($digits, 2)
            : $digits;

        return Student::query()->where('mobile', $tenDigit)->first();
    }

    protected function visitMarker(string $wamid): string
    {
        return 'whatsapp_flow:'.$wamid;
    }
}
