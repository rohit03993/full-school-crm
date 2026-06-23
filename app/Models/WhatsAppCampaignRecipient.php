<?php

namespace App\Models;

use App\Enums\WhatsAppRecipientStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCampaignRecipient extends Model
{
    protected $table = 'whatsapp_campaign_recipients';

    protected $fillable = [
        'whatsapp_campaign_id',
        'student_id',
        'student_call_id',
        'phone',
        'status',
        'template_params',
        'message_sent',
        'provider_response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppRecipientStatus::class,
            'template_params' => 'array',
            'provider_response' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'whatsapp_campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function studentCall(): BelongsTo
    {
        return $this->belongsTo(StudentCall::class);
    }
}
