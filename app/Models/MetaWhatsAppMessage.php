<?php

namespace App\Models;

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
        'template_name',
        'language',
        'body_preview',
        'message_type',
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
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
