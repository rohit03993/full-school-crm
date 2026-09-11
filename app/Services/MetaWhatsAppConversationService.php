<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\WhatsAppContactKind;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\WhatsAppCampaignRecipient;
use App\Support\MetaWhatsAppConversation;
use App\Support\MetaWhatsAppInboundMessageParser;
use App\Support\WhatsAppInboxContact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MetaWhatsAppConversationService
{
    public function __construct(
        protected StudentWhatsAppThreadService $thread,
        protected WhatsAppInboxContactResolver $contacts,
    ) {}

    /**
     * @return Collection<int, MetaWhatsAppConversation>
     */
    public function recentConversations(?string $search = null, int $limit = 500): Collection
    {
        if (! Schema::hasTable('meta_whatsapp_messages')) {
            return $this->conversationsFromCampaignsOnly($search, $limit);
        }

        $latestIds = MetaWhatsAppMessage::query()
            ->selectRaw('MAX(id) as id')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->pluck('id');

        $latestMeta = MetaWhatsAppMessage::query()
            ->with('student:id,name,mobile,status')
            ->whereIn('id', $latestIds)
            ->orderByDesc('created_at')
            ->get();

        $phones = $latestMeta
            ->map(fn (MetaWhatsAppMessage $message): string => $this->thread->normalizePhoneForStorage((string) $message->phone))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $resolvedContacts = $this->contacts->resolveMany($phones);

        $conversations = collect();
        $seenStudentIds = [];
        $seenPhones = [];

        foreach ($latestMeta as $message) {
            $phone = $this->thread->normalizePhoneForStorage((string) $message->phone);

            if ($phone === '' || isset($seenPhones[$phone])) {
                continue;
            }

            $seenPhones[$phone] = true;
            $contact = $resolvedContacts->get($phone) ?? WhatsAppInboxContact::unknown();
            $student = $contact->student ?? $message->student;

            if ($student) {
                $seenStudentIds[$student->id] = true;
            }

            $conversations->push($this->conversationFromMetaMessage($message, $contact, $phone));
        }

        WhatsAppCampaignRecipient::query()
            ->with(['student:id,name,mobile,status', 'campaign.template'])
            ->whereNotNull('student_id')
            ->when($seenStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', array_keys($seenStudentIds)))
            ->orderByDesc('updated_at')
            ->limit(max(200, $limit))
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

                $contact = $phone !== ''
                    ? $this->contacts->resolve($phone)
                    : $this->contactFromStudentOnly($student);

                $conversations->push($this->conversationFromCampaignRecipient($recipient, $contact));
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
            ->limit(max(200, $limit))
            ->get()
            ->unique('student_id')
            ->map(function (WhatsAppCampaignRecipient $recipient): ?MetaWhatsAppConversation {
                $student = $recipient->student;

                if (! $student) {
                    return null;
                }

                $phone = $this->thread->normalizePhoneForStorage((string) $student->mobile);
                $contact = $phone !== ''
                    ? $this->contacts->resolve($phone)
                    : $this->contactFromStudentOnly($student);

                return $this->conversationFromCampaignRecipient($recipient, $contact);
            })
            ->filter()
            ->values();

        return $this->filterAndSort($conversations, $search, $limit);
    }

    protected function conversationFromMetaMessage(
        MetaWhatsAppMessage $message,
        WhatsAppInboxContact $contact,
        ?string $normalizedPhone = null,
    ): MetaWhatsAppConversation {
        $messageType = Schema::hasColumn('meta_whatsapp_messages', 'message_type')
            ? (string) ($message->message_type ?? 'text')
            : 'text';
        $caption = Schema::hasColumn('meta_whatsapp_messages', 'caption')
            ? (string) ($message->caption ?? '')
            : '';
        $rawPreview = (string) ($message->body_preview ?? '');
        if (str_contains($rawPreview, '{{') && filled($message->whatsapp_campaign_recipient_id)) {
            $messageSent = WhatsAppCampaignRecipient::query()
                ->whereKey($message->whatsapp_campaign_recipient_id)
                ->value('message_sent');
            if (is_string($messageSent) && $messageSent !== '' && ! str_contains($messageSent, '{{')) {
                $rawPreview = $messageSent;
            }
        }

        $preview = MetaWhatsAppInboundMessageParser::previewLabel(
            $messageType,
            $rawPreview,
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

        $lastAt = $message->created_at;
        $phone = $normalizedPhone ?: $this->thread->normalizePhoneForStorage((string) $message->phone);
        $student = $contact->student;

        return new MetaWhatsAppConversation(
            studentId: $student?->id,
            studentName: $contact->displayName,
            phone: $phone,
            phoneDisplay: $this->displayPhone((string) ($student?->mobile ?: $contact->staff?->mobile ?: $phone)),
            preview: $preview,
            lastDirection: $direction,
            lastAt: $lastAt,
            sessionOpen: $student
                ? $this->thread->sessionOpenForStudent($student)
                : $this->thread->sessionOpenForPhone($phone),
            needsReply: $direction === 'inbound',
            isLinked: $contact->isLinked(),
            contactKind: $contact->kind->value,
            contactTags: $contact->tagLabels(),
            staffUserId: $contact->staff?->id,
        );
    }

    protected function conversationFromCampaignRecipient(
        WhatsAppCampaignRecipient $recipient,
        WhatsAppInboxContact $contact,
    ): MetaWhatsAppConversation {
        $preview = trim((string) ($recipient->message_sent ?? ''));

        if ($preview === '') {
            $preview = (string) ($recipient->campaign?->template?->name ?? 'WhatsApp message');
        }

        $student = $contact->student ?? $recipient->student;
        $phone = $this->thread->normalizePhoneForStorage((string) ($student?->mobile ?? ''));

        return new MetaWhatsAppConversation(
            studentId: $student?->id,
            studentName: $contact->displayName,
            phone: $phone,
            phoneDisplay: $this->displayPhone((string) ($student?->mobile ?? $phone)),
            preview: $preview,
            lastDirection: 'outbound',
            lastAt: $recipient->updated_at ?? $recipient->created_at,
            sessionOpen: $student ? $this->thread->sessionOpenForStudent($student) : false,
            needsReply: false,
            isLinked: $contact->isLinked(),
            contactKind: $contact->kind->value,
            contactTags: $contact->tagLabels(),
            staffUserId: $contact->staff?->id,
        );
    }

    protected function contactFromStudentOnly(Student $student): WhatsAppInboxContact
    {
        $kind = $student->status === \App\Enums\StudentStatus::Enquiry
            || (string) $student->status === \App\Enums\StudentStatus::Enquiry->value
            ? WhatsAppContactKind::Lead
            : WhatsAppContactKind::Student;

        return new WhatsAppInboxContact(
            displayName: (string) $student->name,
            kind: $kind,
            tags: [$kind],
            student: $student,
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
                    $tags = strtolower(implode(' ', $conversation->contactTags));

                    return str_contains(strtolower($conversation->studentName), $needle)
                        || str_contains(strtolower($conversation->phoneDisplay), $needle)
                        || str_contains(strtolower($conversation->phone), $needle)
                        || str_contains(strtolower($conversation->preview), $needle)
                        || str_contains($tags, $needle)
                        || str_contains(strtolower($conversation->contactKind), $needle);
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
