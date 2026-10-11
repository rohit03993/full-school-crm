<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInboxView extends Model
{
    protected $table = 'whatsapp_inbox_views';

    protected $fillable = [
        'phone',
        'user_id',
        'meta_whatsapp_message_id',
        'seen_at',
    ];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
