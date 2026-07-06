<?php

namespace App\Models;

use App\Enums\WhatsAppLiveCampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppLiveCampaign extends Model
{
    protected $table = 'whatsapp_live_campaigns';

    protected $fillable = [
        'name',
        'meta_whatsapp_template_id',
        'status',
        'description',
        'default_variables',
        'went_live_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => WhatsAppLiveCampaignStatus::class,
            'default_variables' => 'array',
            'went_live_at' => 'datetime',
        ];
    }

    public function metaTemplate(): BelongsTo
    {
        return $this->belongsTo(MetaWhatsAppTemplate::class, 'meta_whatsapp_template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        return $this->status === WhatsAppLiveCampaignStatus::Live;
    }

    public function templateName(): ?string
    {
        return $this->metaTemplate?->name;
    }

    public function templateLanguage(): string
    {
        return $this->metaTemplate?->language ?? 'en';
    }
}
