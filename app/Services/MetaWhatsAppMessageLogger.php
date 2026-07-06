<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Student;

class MetaWhatsAppMessageLogger
{
    public function __construct(
        protected WhatsAppTemplateParamResolver $paramResolver,
    ) {}

    /**
     * @param  list<string>  $bodyParams
     */
    public function recordOutbound(
        string $phone,
        ?string $wamid,
        string $templateName,
        string $language,
        array $bodyParams,
        MetaWhatsAppMessageStatus $status,
        ?string $statusDetail = null,
        ?array $payload = null,
        ?int $studentId = null,
        ?string $bodyPreview = null,
    ): MetaWhatsAppMessage {
        $preview = $bodyPreview ?? $this->buildOutboundPreview($templateName, $language, $bodyParams);

        return MetaWhatsAppMessage::query()->create([
            'wamid' => $wamid,
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => $this->normalizePhone($phone),
            'student_id' => $studentId ?? $this->guessStudentId($phone),
            'template_name' => $templateName !== '' ? $templateName : null,
            'language' => $language !== '' ? $language : null,
            'body_preview' => mb_substr($preview, 0, 500),
            'status' => $status->value,
            'status_detail' => $statusDetail,
            'payload' => $payload,
            'status_at' => now(),
        ]);
    }

    public function recordInbound(
        string $phone,
        ?string $wamid,
        string $bodyPreview,
        ?array $payload = null,
    ): MetaWhatsAppMessage {
        return MetaWhatsAppMessage::query()->create([
            'wamid' => $wamid,
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => $this->normalizePhone($phone),
            'student_id' => $this->guessStudentId($phone),
            'body_preview' => mb_substr($bodyPreview, 0, 500),
            'status' => MetaWhatsAppMessageStatus::Received->value,
            'payload' => $payload,
            'status_at' => now(),
        ]);
    }

    public function applyWebhookStatus(string $wamid, MetaWhatsAppMessageStatus $status, ?string $detail = null, ?array $payload = null): void
    {
        $message = MetaWhatsAppMessage::query()->where('wamid', $wamid)->first();

        if (! $message) {
            return;
        }

        if ($this->shouldIgnoreStatusDowngrade($message->status, $status->value)) {
            return;
        }

        $message->update([
            'status' => $status->value,
            'status_detail' => $detail,
            'payload' => $payload ?? $message->payload,
            'status_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $bodyParams
     */
    protected function buildOutboundPreview(string $templateName, string $language, array $bodyParams): string
    {
        $metaTemplate = MetaWhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('is_active', true)
            ->when($language !== '', fn ($query) => $query->where('language', $language))
            ->orderByDesc('synced_at')
            ->first();

        if ($metaTemplate && filled($metaTemplate->body)) {
            $preview = $this->paramResolver->buildPreview((string) $metaTemplate->body, $bodyParams);

            if (filled($preview)) {
                return $preview;
            }

            return (string) $metaTemplate->body;
        }

        if ($bodyParams !== []) {
            return implode(' · ', $bodyParams);
        }

        return $templateName;
    }

    protected function shouldIgnoreStatusDowngrade(string $current, string $incoming): bool
    {
        $order = [
            MetaWhatsAppMessageStatus::Queued->value => 0,
            MetaWhatsAppMessageStatus::Sent->value => 1,
            MetaWhatsAppMessageStatus::Delivered->value => 2,
            MetaWhatsAppMessageStatus::Read->value => 3,
            MetaWhatsAppMessageStatus::Failed->value => 99,
            MetaWhatsAppMessageStatus::Received->value => 99,
        ];

        $currentRank = $order[$current] ?? 0;
        $incomingRank = $order[$incoming] ?? 0;

        return $incoming !== MetaWhatsAppMessageStatus::Failed->value
            && $currentRank > $incomingRank;
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }

    protected function guessStudentId(string $phone): ?int
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $tenDigit = strlen($digits) === 12 && str_starts_with($digits, '91')
            ? substr($digits, 2)
            : $digits;

        return Student::query()
            ->where('mobile', $tenDigit)
            ->value('id');
    }
}
