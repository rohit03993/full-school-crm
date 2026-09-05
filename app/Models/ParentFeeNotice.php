<?php

namespace App\Models;

use App\Enums\ParentFeeNoticeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentFeeNotice extends Model
{
    protected $fillable = [
        'student_id',
        'batch_id',
        'amount',
        'due_date',
        'whatsapp_campaign_id',
        'whatsapp_campaign_recipient_id',
        'sent_by_user_id',
        'sent_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'status' => ParentFeeNoticeStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function whatsappCampaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class);
    }

    public function whatsappCampaignRecipient(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaignRecipient::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
