<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppContactKind;
use App\Enums\WhatsAppRecipientStatus;
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
        $lastOutboundFailed = $this->lastOutboundFailedByPhone($latestMeta);
        $campaignSentPreviews = $this->campaignSentPreviews($latestMeta);

        $metaRows = [];
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

            $metaRows[] = [
                'message' => $message,
                'contact' => $contact,
                'phone' => $phone,
                'student' => $student,
                'failed' => (bool) ($lastOutboundFailed[$phone] ?? $lastOutboundFailed[(string) $message->phone] ?? false),
            ];
        }

        $campaignRecipients = WhatsAppCampaignRecipient::query()
            ->with(['student:id,name,mobile,status', 'campaign.template'])
            ->whereNotNull('student_id')
            ->when($seenStudentIds !== [], fn ($query) => $query->whereNotIn('student_id', array_keys($seenStudentIds)))
            ->orderByDesc('updated_at')
            ->limit(max(200, $limit))
            ->get()
            ->unique('student_id')
            ->filter(fn (WhatsAppCampaignRecipient $recipient): bool => $recipient->student !== null)
            ->values();

        $campaignPhones = $campaignRecipients
            ->map(fn (WhatsAppCampaignRecipient $recipient): string => $this->thread->normalizePhoneForStorage((string) $recipient->student?->mobile))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $campaignContacts = $this->contacts->resolveMany($campaignPhones);

        $sessions = $this->openSessionsFor($metaRows, $campaignRecipients);

        $conversations = collect();

        foreach ($metaRows as $row) {
            $conversations->push($this->conversationFromMetaMessage(
                $row['message'],
                $row['contact'],
                $row['phone'],
                $row['failed'],
                $this->sessionIsOpen($row['student'], $row['phone'], $sessions),
                $campaignSentPreviews,
            ));
        }

        foreach ($campaignRecipients as $recipient) {
            $student = $recipient->student;

            if (! $student) {
                continue;
            }

            $phone = $this->thread->normalizePhoneForStorage((string) $student->mobile);

            if ($phone !== '' && isset($seenPhones[$phone])) {
                continue;
            }

            if ($phone !== '') {
                $seenPhones[$phone] = true;
            }

            $contact = $phone !== ''
                ? ($campaignContacts->get($phone) ?? WhatsAppInboxContact::unknown())
                : $this->contactFromStudentOnly($student);

            $conversations->push($this->conversationFromCampaignRecipient(
                $recipient,
                $contact,
                $this->sessionIsOpen($student, $phone, $sessions),
            ));
        }

        return $this->filterAndSort($conversations, $search, $limit);
    }

    /**
     * @return Collection<int, MetaWhatsAppConversation>
     */
    protected function conversationsFromCampaignsOnly(?string $search, int $limit): Collection
    {
        $recipients = WhatsAppCampaignRecipient::query()
            ->with(['student:id,name,mobile,status', 'campaign.template'])
            ->whereNotNull('student_id')
            ->orderByDesc('updated_at')
            ->limit(max(200, $limit))
            ->get()
            ->unique('student_id')
            ->filter(fn (WhatsAppCampaignRecipient $recipient): bool => $recipient->student !== null)
            ->values();

        $phones = $recipients
            ->map(fn (WhatsAppCampaignRecipient $recipient): string => $this->thread->normalizePhoneForStorage((string) $recipient->student?->mobile))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $contacts = $this->contacts->resolveMany($phones);
        $sessions = $this->openSessionsFor([], $recipients);

        $conversations = $recipients
            ->map(function (WhatsAppCampaignRecipient $recipient) use ($contacts, $sessions): MetaWhatsAppConversation {
                $student = $recipient->student;
                $phone = $this->thread->normalizePhoneForStorage((string) $student?->mobile);
                $contact = $phone !== ''
                    ? ($contacts->get($phone) ?? WhatsAppInboxContact::unknown())
                    : $this->contactFromStudentOnly($student);

                return $this->conversationFromCampaignRecipient(
                    $recipient,
                    $contact,
                    $this->sessionIsOpen($student, $phone, $sessions),
                );
            })
            ->values();

        return $this->filterAndSort($conversations, $search, $limit);
    }

    /**
     * Last school send per chat phone: true when Meta marked that outbound row failed.
     *
     * @param  Collection<int, MetaWhatsAppMessage>  $latestMeta
     * @return array<string, bool>
     */
    protected function lastOutboundFailedByPhone(Collection $latestMeta): array
    {
        $phones = $latestMeta
            ->flatMap(function (MetaWhatsAppMessage $message): array {
                $raw = (string) $message->phone;
                $normalized = $this->thread->normalizePhoneForStorage($raw);

                return array_values(array_filter([$raw, $normalized]));
            })
            ->unique()
            ->values()
            ->all();

        if ($phones === []) {
            return [];
        }

        $latestOutboundIds = MetaWhatsAppMessage::query()
            ->selectRaw('MAX(id) as id')
            ->where('direction', MetaWhatsAppMessageDirection::Outbound->value)
            ->whereIn('phone', $phones)
            ->groupBy('phone')
            ->pluck('id');

        $map = [];

        MetaWhatsAppMessage::query()
            ->whereIn('id', $latestOutboundIds)
            ->get(['phone', 'status'])
            ->each(function (MetaWhatsAppMessage $message) use (&$map): void {
                $failed = strtolower((string) $message->status) === MetaWhatsAppMessageStatus::Failed->value;
                $raw = (string) $message->phone;
                $normalized = $this->thread->normalizePhoneForStorage($raw);
                $map[$raw] = $failed;

                if ($normalized !== '') {
                    $map[$normalized] = $failed;
                }
            });

        return $map;
    }

    public function inboxChangeStamp(): string
    {
        $messages = Schema::hasTable('meta_whatsapp_messages')
            ? MetaWhatsAppMessage::query()->selectRaw('MAX(id) as max_id, MAX(updated_at) as max_updated')->first()
            : null;
        $campaigns = Schema::hasTable('whatsapp_campaign_recipients')
            ? WhatsAppCampaignRecipient::query()->selectRaw('MAX(id) as max_id, MAX(updated_at) as max_updated')->first()
            : null;

        return ($messages?->max_id ?? '').'|'.($messages?->max_updated ?? '')
            .'|'.($campaigns?->max_id ?? '').'|'.($campaigns?->max_updated ?? '');
    }

    /**
     * @param  list<array{student: ?Student, phone: string}>  $metaRows
     * @param  Collection<int, WhatsAppCampaignRecipient>  $campaignRecipients
     * @return array{phones: array<string, true>, students: array<int, true>}
     */
    protected function openSessionsFor(array $metaRows, Collection $campaignRecipients): array
    {
        $phones = [];
        $studentIds = [];

        foreach ($metaRows as $row) {
            $this->collectSessionKeys($row['student'] ?? null, (string) ($row['phone'] ?? ''), $phones, $studentIds);
        }

        foreach ($campaignRecipients as $recipient) {
            $student = $recipient->student;
            $phone = $student
                ? $this->thread->normalizePhoneForStorage((string) $student->mobile)
                : '';
            $this->collectSessionKeys($student, $phone, $phones, $studentIds);
        }

        return $this->thread->openSessionLookup($phones, $studentIds);
    }

    /**
     * @param  list<string>  $phones
     * @param  list<int>  $studentIds
     */
    protected function collectSessionKeys(?Student $student, string $phone, array &$phones, array &$studentIds): void
    {
        if ($student) {
            $studentPhone = $this->thread->normalizePhoneForStorage((string) $student->mobile);

            if ($studentPhone === '') {
                return;
            }

            $phones[] = $studentPhone;
            $studentIds[] = $student->id;

            return;
        }

        if ($phone !== '') {
            $phones[] = $phone;
        }
    }

    /**
     * @param  array{phones: array<string, true>, students: array<int, true>}  $sessions
     */
    protected function sessionIsOpen(?Student $student, string $phone, array $sessions): bool
    {
        if ($student) {
            $studentPhone = $this->thread->normalizePhoneForStorage((string) $student->mobile);

            if ($studentPhone === '') {
                return false;
            }

            return isset($sessions['phones'][$studentPhone]) || isset($sessions['students'][$student->id]);
        }

        return $phone !== '' && isset($sessions['phones'][$phone]);
    }

    /**
     * @param  Collection<int, MetaWhatsAppMessage>  $latestMeta
     * @return array<int, ?string>
     */
    protected function campaignSentPreviews(Collection $latestMeta): array
    {
        $ids = $latestMeta
            ->pluck('whatsapp_campaign_recipient_id')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return WhatsAppCampaignRecipient::query()
            ->whereIn('id', $ids)
            ->get(['id', 'message_sent'])
            ->mapWithKeys(fn (WhatsAppCampaignRecipient $recipient): array => [
                (int) $recipient->id => $recipient->message_sent,
            ])
            ->all();
    }

    /**
     * @param  array<int, ?string>|null  $campaignSentPreviews
     */
    protected function conversationFromMetaMessage(
        MetaWhatsAppMessage $message,
        WhatsAppInboxContact $contact,
        ?string $normalizedPhone = null,
        bool $lastSendFailed = false,
        bool $sessionOpen = false,
        ?array $campaignSentPreviews = null,
    ): MetaWhatsAppConversation {
        $messageType = Schema::hasColumn('meta_whatsapp_messages', 'message_type')
            ? (string) ($message->message_type ?? 'text')
            : 'text';
        $caption = Schema::hasColumn('meta_whatsapp_messages', 'caption')
            ? (string) ($message->caption ?? '')
            : '';
        $rawPreview = (string) ($message->body_preview ?? '');
        if (str_contains($rawPreview, '{{') && filled($message->whatsapp_campaign_recipient_id)) {
            $recipientId = (int) $message->whatsapp_campaign_recipient_id;
            $messageSent = $campaignSentPreviews === null
                ? WhatsAppCampaignRecipient::query()->whereKey($recipientId)->value('message_sent')
                : ($campaignSentPreviews[$recipientId] ?? null);
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
            sessionOpen: $sessionOpen,
            needsReply: $direction === 'inbound',
            lastSendFailed: $lastSendFailed,
            isLinked: $contact->isLinked(),
            contactKind: $contact->kind->value,
            contactTags: $contact->tagLabels(),
            staffUserId: $contact->staff?->id,
        );
    }

    protected function conversationFromCampaignRecipient(
        WhatsAppCampaignRecipient $recipient,
        WhatsAppInboxContact $contact,
        bool $sessionOpen = false,
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
            sessionOpen: $sessionOpen,
            needsReply: false,
            lastSendFailed: $recipient->status === WhatsAppRecipientStatus::Failed,
            isLinked: $contact->isLinked(),
            contactKind: $contact->kind->value,
            contactTags: $contact->tagLabels(),
            staffUserId: $contact->staff?->id,
        );
    }

    protected function contactFromStudentOnly(Student $student): WhatsAppInboxContact
    {
        $status = $student->status instanceof StudentStatus
            ? $student->status
            : (is_string($student->status) ? StudentStatus::tryFrom($student->status) : null);

        $kind = $status === StudentStatus::Enquiry
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
