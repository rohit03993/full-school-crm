<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\WhatsAppCampaignRecipient;
use App\Support\MetaWhatsAppInboundMessageParser;
use App\Support\MetaWhatsAppConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetaWhatsAppConversationService
{
    public function __construct(
        protected StudentWhatsAppThreadService $thread,
    ) {}

    /**
     * @return Collection<int, MetaWhatsAppConversation>
     */
    public function recentConversations(?string $search = null, int $limit = 50): Collection
    {
        if (! Schema::hasTable('meta_whatsapp_messages')) {
            return $this->conversationsFromCampaignsOnly($search, $limit);
        }

        $latestIds = MetaWhatsAppMessage::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('phone')
            ->pluck('id');

        $latestMeta = MetaWhatsAppMessage::query()
            ->with('student:id,name,mobile,status')
            ->whereIn('id', $latestIds)
            ->orderByDesc(DB::raw('COALESCE(status_at, created_at)'))
            ->get();

        $conversations = collect();
        $seenStudentIds = [];
        $seenPhones = [];

        foreach ($latestMeta as $message) {
            $phone = $this->thread->normalizePhoneForStorage((string) $message->phone);

            if ($phone === '' || isset($seenPhones[$phone])) {
                continue;
            }

            $seenPhones[$phone] = true;
            $student = $message->student ?? $this->thread->findStudentByPhone($phone);

            if ($student) {
                $seenStudentIds[$student->id] = true;
            }

            $conversations->push($this->conversationFromMetaMessage($message, $student, $phone));
        }

        WhatsAppCampaignRecipient::query()
            ->with(['student:id,name,mobile,status', 'campaign.template'])
            ->whereNotNull('student_id')
            ->when($seenStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', array_keys($seenStudentIds)))
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->unique('student_id')
            ->each(function (WhatsAppCampaignRecipient $recipient) use ($conversations, &$seenPhones): void {
                $student = $recipient->student;

                if (! $student) {
                    return;
                }

                $phone = $this->thread->normalizePhoneForStorage((string) $student->mobile);

                if ($phone !== '' && isset($seenPhones[$phone])) {
                    return;
                }

                if ($phone !== '') {
                    $seenPhones[$phone] = true;
                }

                $conversations->push($this->conversationFromCampaignRecipient($recipient, $student));
            });

        return $this->filterAndSort($conversations, $search, $limit);
    }

    /**
     * @return Collection<int, MetaWhatsAppConversation>
     */
    protected function conversationsFromCampaignsOnly(?string $search, int $limit): Collection
    {
        $conversations = WhatsAppCampaignRecipient::query()
            ->with(['student:id,name,mobile,status', 'campaign.template'])
            ->whereNotNull('student_id')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->unique('student_id')
            ->map(function (WhatsAppCampaignRecipient $recipient): ?MetaWhatsAppConversation {
                $student = $recipient->student;

                return $student ? $this->conversationFromCampaignRecipient($recipient, $student) : null;
            })
            ->filter()
            ->values();

        return $this->filterAndSort($conversations, $search, $limit);
    }

    protected function conversationFromMetaMessage(
        MetaWhatsAppMessage $message,
        ?Student $student,
        ?string $normalizedPhone = null,
    ): MetaWhatsAppConversation {
        $messageType = Schema::hasColumn('meta_whatsapp_messages', 'message_type')
            ? (string) ($message->message_type ?? 'text')
            : 'text';
        $caption = Schema::hasColumn('meta_whatsapp_messages', 'caption')
            ? (string) ($message->caption ?? '')
            : '';
        $preview = MetaWhatsAppInboundMessageParser::previewLabel(
            $messageType,
            (string) ($message->body_preview ?? ''),
            $caption,
        );

        if ($preview === '' && filled($message->template_name)) {
            $preview = (string) $message->template_name;
        }

        if ($preview === '') {
            $preview = 'WhatsApp message';
        }

        $direction = $message->direction === MetaWhatsAppMessageDirection::Inbound->value
            ? 'inbound'
            : 'outbound';

        $lastAt = $message->status_at ?? $message->created_at;
        $phone = $normalizedPhone ?: $this->thread->normalizePhoneForStorage((string) $message->phone);
        $linked = $student !== null;

        return new MetaWhatsAppConversation(
            studentId: $student?->id,
            studentName: $linked ? (string) $student->name : 'Unknown contact',
            phone: $phone,
            phoneDisplay: $this->displayPhone((string) ($student?->mobile ?: $phone)),
            preview: $preview,
            lastDirection: $direction,
            lastAt: $lastAt,
            sessionOpen: $linked
                ? $this->thread->sessionOpenForStudent($student)
                : $this->thread->sessionOpenForPhone($phone),
            needsReply: $direction === 'inbound',
            isLinked: $linked,
        );
    }

    protected function conversationFromCampaignRecipient(
        WhatsAppCampaignRecipient $recipient,
        Student $student,
    ): MetaWhatsAppConversation {
        $preview = trim((string) ($recipient->message_sent ?? ''));

        if ($preview === '') {
            $preview = (string) ($recipient->campaign?->template?->name ?? 'WhatsApp message');
        }

        return new MetaWhatsAppConversation(
            studentId: $student->id,
            studentName: (string) $student->name,
            phone: $this->thread->normalizePhoneForStorage((string) $student->mobile),
            phoneDisplay: $this->displayPhone((string) $student->mobile),
            preview: $preview,
            lastDirection: 'outbound',
            lastAt: $recipient->updated_at ?? $recipient->created_at,
            sessionOpen: $this->thread->sessionOpenForStudent($student),
            needsReply: false,
            isLinked: true,
        );
    }

    /**
     * @param  Collection<int, MetaWhatsAppConversation>  $conversations
     * @return Collection<int, MetaWhatsAppConversation>
     */
    protected function filterAndSort(Collection $conversations, ?string $search, int $limit): Collection
    {
        $needle = strtolower(trim((string) $search));

        return $conversations
            ->when($needle !== '', function (Collection $rows) use ($needle): Collection {
                return $rows->filter(function (MetaWhatsAppConversation $conversation) use ($needle): bool {
                    return str_contains(strtolower($conversation->studentName), $needle)
                        || str_contains(strtolower($conversation->phoneDisplay), $needle)
                        || str_contains(strtolower($conversation->phone), $needle)
                        || str_contains(strtolower($conversation->preview), $needle);
                });
            })
            ->sortByDesc(fn (MetaWhatsAppConversation $conversation): int => $conversation->lastAt?->timestamp ?? 0)
            ->values()
            ->take(max(1, $limit));
    }

    protected function displayPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }

        return $digits !== '' ? $digits : $phone;
    }
}
