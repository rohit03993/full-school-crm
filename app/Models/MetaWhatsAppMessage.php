<?php

namespace App\Models;

use App\Enums\WhatsAppSendActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaWhatsAppMessage extends Model
{
    protected $table = 'meta_whatsapp_messages';

    protected $fillable = [
        'wamid',
        'direction',
        'phone',
        'student_id',
        'sent_by_user_id',
        'send_actor',
        'template_name',
        'language',
        'body_preview',
        'message_type',
        'conversation_category',
        'message_source',
        'estimated_cost_inr',
        'whatsapp_campaign_recipient_id',
        'media_id',
        'media_path',
        'media_mime_type',
        'media_filename',
        'caption',
        'status',
        'status_detail',
        'payload',
        'status_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status_at' => 'datetime',
            'estimated_cost_inr' => 'float',
            'send_actor' => WhatsAppSendActor::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Human label for inbox / history (CRM-only attribution).
     */
    public function senderLabel(): string
    {
        if ($this->direction === 'inbound') {
            return 'Parent';
        }

        $actor = $this->send_actor instanceof WhatsAppSendActor
            ? $this->send_actor
            : WhatsAppSendActor::tryFrom((string) $this->send_actor);

        if ($actor === WhatsAppSendActor::System) {
            $template = strtolower((string) ($this->template_name ?? ''));

            return str_contains($template, 'otp') || str_contains($template, 'login')
                ? 'System · OTP'
                : 'System';
        }

        if ($actor === WhatsAppSendActor::Automatic) {
            return 'Automatic';
        }

        if ($actor === WhatsAppSendActor::Staff || filled($this->sent_by_user_id)) {
            $name = $this->relationLoaded('sentBy')
                ? $this->sentBy?->name
                : $this->sentBy()->value('name');

            return filled($name) ? 'Sent by '.$name : 'Staff';
        }

        return 'Unknown';
    }
}
