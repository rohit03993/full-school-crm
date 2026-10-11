<?php

namespace App\Services;

use App\Models\MetaWhatsAppMessage;
use App\Models\WhatsAppInboxView;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

class WhatsAppInboxViewService
{
    public function recordOpen(string $phone, ?int $userId): bool
    {
        if ($phone === '' || $userId === null || $userId <= 0 || ! Schema::hasTable('whatsapp_inbox_views')) {
            return false;
        }

        $messageId = $this->latestMessageId($phone);

        $alreadySeen = WhatsAppInboxView::query()
            ->where('phone', $phone)
            ->where('user_id', $userId)
            ->when(
                $messageId === null,
                fn ($query) => $query->whereNull('meta_whatsapp_message_id'),
                fn ($query) => $query->where('meta_whatsapp_message_id', $messageId),
            )
            ->exists();

        if ($alreadySeen) {
            return false;
        }

        try {
            WhatsAppInboxView::query()->create([
                'phone' => $phone,
                'user_id' => $userId,
                'meta_whatsapp_message_id' => $messageId,
                'seen_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, ?int>  $lastMessageIdByPhone
     * @return array<string, list<array{name: string, label: string}>>
     */
    public function seenByPhone(array $lastMessageIdByPhone): array
    {
        if ($lastMessageIdByPhone === [] || ! Schema::hasTable('whatsapp_inbox_views')) {
            return [];
        }

        $views = WhatsAppInboxView::query()
            ->with('user:id,name')
            ->whereIn('phone', array_keys($lastMessageIdByPhone))
            ->orderBy('seen_at')
            ->get();

        $grouped = [];

        foreach ($views as $view) {
            $expected = $lastMessageIdByPhone[$view->phone] ?? null;
            $actual = $view->meta_whatsapp_message_id;

            if ($expected === null) {
                if ($actual !== null) {
                    continue;
                }
            } elseif ((int) $actual !== (int) $expected) {
                continue;
            }

            $name = trim((string) ($view->user?->name ?? ''));

            if ($name === '') {
                $name = 'Staff';
            }

            $grouped[$view->phone][] = [
                'name' => $name,
                'label' => 'Seen by '.$name.' · '.$view->seen_at->format('j M, g:i A'),
            ];
        }

        return $grouped;
    }

    public function latestMessageId(string $phone): ?int
    {
        if ($phone === '' || ! Schema::hasTable('meta_whatsapp_messages')) {
            return null;
        }

        $id = MetaWhatsAppMessage::query()->where('phone', $phone)->max('id');

        return $id ? (int) $id : null;
    }
}
