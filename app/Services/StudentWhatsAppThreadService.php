<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\WhatsAppCampaignRecipient;
use App\Support\StudentWhatsAppThreadItem;
use Illuminate\Support\Collection;

class StudentWhatsAppThreadService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return Collection<int, StudentWhatsAppThreadItem>
     */
    public function threadForStudent(Student $student, int $limit = 50): Collection
    {
        $phone = $this->normalizePhone((string) $student->mobile);

        $campaignMessages = WhatsAppCampaignRecipient::query()
            ->where('student_id', $student->id)
            ->with(['campaign.template'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppCampaignRecipient $row): StudentWhatsAppThreadItem => new StudentWhatsAppThreadItem(
                key: 'campaign-'.$row->id,
                source: 'campaign',
                direction: 'outbound',
                body: (string) ($row->message_sent ?? $row->campaign?->template?->name ?? 'WhatsApp message'),
                status: (string) ($row->status?->value ?? 'pending'),
                statusLabel: $row->status?->label() ?? 'Pending',
                at: $row->created_at,
                templateName: $row->campaign?->template?->name,
                provider: data_get($row->provider_response, 'provider', 'pal_digital'),
            ));

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

        $metaMessages = $metaQuery
            ->get()
            ->map(function (MetaWhatsAppMessage $row): StudentWhatsAppThreadItem {
                $status = MetaWhatsAppMessageStatus::tryFrom($row->status);

                return new StudentWhatsAppThreadItem(
                    key: 'meta-'.$row->id,
                    source: 'meta',
                    direction: $row->direction,
                    body: (string) ($row->body_preview ?? ''),
                    status: $row->status,
                    statusLabel: $status?->label() ?? ucfirst($row->status),
                    at: $row->status_at ?? $row->created_at,
                    templateName: $row->template_name,
                    provider: 'meta',
                );
            });

        return $campaignMessages
            ->concat($metaMessages)
            ->filter(fn (StudentWhatsAppThreadItem $item): bool => $item->at !== null)
            ->sortBy(fn (StudentWhatsAppThreadItem $item) => $item->at?->timestamp ?? 0)
            ->values()
            ->take($limit);
    }

    public function sessionOpenForStudent(Student $student): bool
    {
        if (! $this->meta->isConfigured()) {
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

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        return $digits;
    }
}
