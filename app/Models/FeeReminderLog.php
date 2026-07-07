<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeReminderLog extends Model
{
    protected $fillable = [
        'student_id',
        'fee_installment_id',
        'whatsapp_campaign_id',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeInstallment(): BelongsTo
    {
        return $this->belongsTo(FeeInstallment::class);
    }

    public function whatsappCampaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class);
    }
}
