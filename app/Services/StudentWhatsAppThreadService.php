<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Student;
use App\Models\WhatsAppCampaignRecipient;
use App\Support\StudentWhatsAppThreadItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudentWhatsAppThreadService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected WhatsAppTemplateParamResolver $paramResolver,
    ) {}

    /**
     * @return Collection<int, StudentWhatsAppThreadItem>
     */
    public function threadForStudent(Student $student, int $limit = 50): Collection
    {
        $phone = $this->normalizePhone((string) $student->mobile);
        $metaSupported = $this->metaMessagesSupported();

        $campaignMessages = WhatsAppCampaignRecipient::query()
            ->where('student_id', $student->id)
            ->with(['campaign.template'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->filter(function (WhatsAppCampaignRecipient $row) use ($metaSupported): bool {
                if (! $metaSupported) {
                    return true;
                }

                return $row->status !== WhatsAppRecipientStatus::Sent;
            })
            ->map(fn (WhatsAppCampaignRecipient $row): StudentWhatsAppThreadItem => new StudentWhatsAppThreadItem(
                key: 'campaign-'.$row->id,
                source: 'campaign',
                direction: 'outbound',
                body: $this->campaignDisplayBody($row),
                status: (string) ($row->status?->value ?? 'pending'),
                statusLabel: $row->status?->label() ?? 'Pending',
                at: $row->created_at,
                templateName: null,
                provider: 'meta',
                errorMessage: $row->status?->value === 'failed' ? (string) ($row->error_message ?? '') : null,
            ));

        $metaMessages = $metaSupported
            ? $this->loadMetaMessages($student, $phone, $limit)
            : collect();

        return $campaignMessages
            ->concat($metaMessages)
            ->filter(fn (StudentWhatsAppThreadItem $item): bool => $item->at !== null)
            ->sortBy(fn (StudentWhatsAppThreadItem $item) => $item->at?->timestamp ?? 0)
            ->values()
            ->take($limit);
    }

    public function sessionOpenForStudent(Student $student): bool
    {
        if (! $this->metaMessagesSupported() || ! $this->meta->isConfigured()) {
            return false;
        }

        $phone = $this->normalizePhone((string) $student->mobile);

        if ($phone === '') {
            return false;
        }

        $lastInbound = MetaWhatsAppMessage::query()
            ->where('direction', MetaWhatsAppMessageDirection::Inbound->value)
            ->where(function ($query) use ($student, $phone): void {
                $query->where('student_id', $student->id)
                    ->orWhere('phone', $phone);
            })
            ->latest('created_at')
            ->first();

        return $lastInbound?->created_at?->gt(now()->subHours(24)) ?? false;
    }

    protected function metaMessagesSupported(): bool
    {
        return Schema::hasTable('meta_whatsapp_messages');
    }

    /**
     * @return Collection<int, StudentWhatsAppThreadItem>
     */
    protected function loadMetaMessages(Student $student, string $phone, int $limit): Collection
    {
        $metaQuery = MetaWhatsAppMessage::query()
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($phone !== '') {
            $metaQuery->where(function ($query) use ($student, $phone): void {
                $query->where('student_id', $student->id)
                    ->orWhere('phone', $phone);
            });
        } else {
            $metaQuery->where('student_id', $student->id);
        }

        return $metaQuery
            ->get()
            ->map(function (MetaWhatsAppMessage $row): StudentWhatsAppThreadItem {
                $status = MetaWhatsAppMessageStatus::tryFrom($row->status);

                return new StudentWhatsAppThreadItem(
                    key: 'meta-'.$row->id,
                    source: 'meta',
                    direction: $row->direction,
                    body: $this->metaDisplayBody($row),
                    status: $row->status,
                    statusLabel: $status?->label() ?? ucfirst($row->status),
                    at: $row->status_at ?? $row->created_at,
                    templateName: null,
                    provider: 'meta',
                );
            });
    }

    protected function campaignDisplayBody(WhatsAppCampaignRecipient $row): string
    {
        $templateName = (string) ($row->campaign?->template?->name ?? '');
        $messageSent = trim((string) ($row->message_sent ?? ''));

        if ($messageSent !== '' && ! $this->isTemplateSlug($messageSent, $templateName)) {
            return $messageSent;
        }

        $templateBody = trim((string) ($row->campaign?->template?->body ?? ''));

        if ($templateBody !== '') {
            $params = is_array($row->template_params) ? array_values($row->template_params) : [];
            $preview = $this->paramResolver->buildPreview($templateBody, $params);

            if (filled($preview)) {
                return $preview;
            }

            return $templateBody;
        }

        $metaBody = $this->metaTemplateBody($templateName);

        if ($metaBody !== null) {
            return $metaBody;
        }

        return $messageSent !== '' ? $messageSent : ($templateName !== '' ? $templateName : 'WhatsApp message');
    }

    protected function metaDisplayBody(MetaWhatsAppMessage $row): string
    {
        $preview = trim((string) ($row->body_preview ?? ''));
        $templateName = (string) ($row->template_name ?? '');

        if ($preview !== '' && ! $this->isTemplateSlug($preview, $templateName)) {
            return $preview;
        }

        if ($templateName !== '') {
            $metaTemplate = $this->metaTemplate($templateName, (string) ($row->language ?? ''));

            if ($metaTemplate && filled($metaTemplate->body)) {
                return (string) $metaTemplate->body;
            }
        }

        return $preview !== '' ? $preview : 'Message';
    }

    protected function metaTemplateBody(string $templateName): ?string
    {
        if ($templateName === '') {
            return null;
        }

        $metaTemplate = $this->metaTemplate($templateName, '');

        return filled($metaTemplate?->body) ? (string) $metaTemplate->body : null;
    }

    protected function metaTemplate(string $name, string $language): ?MetaWhatsAppTemplate
    {
        $query = MetaWhatsAppTemplate::query()
            ->where('name', $name)
            ->where('is_active', true);

        if ($language !== '') {
            $match = (clone $query)->where('language', $language)->first();

            if ($match) {
                return $match;
            }
        }

        return $query->orderByDesc('synced_at')->first();
    }

    protected function isTemplateSlug(string $text, string $templateName): bool
    {
        if ($templateName === '') {
            return false;
        }

        return strcasecmp(trim($text), trim($templateName)) === 0;
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }
}
