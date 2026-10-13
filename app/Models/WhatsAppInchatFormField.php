<?php

namespace App\Models;

use App\Enums\WhatsAppInchatFieldType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppInchatFormField extends Model
{
    protected $table = 'whatsapp_inchat_form_fields';
    protected $fillable = [
        'whatsapp_inchat_form_id',
        'sort',
        'field_type',
        'label',
        'is_required',
        'choices',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => WhatsAppInchatFieldType::class,
            'is_required' => 'boolean',
            'choices' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInchatForm::class, 'whatsapp_inchat_form_id');
    }

    public function answerKey(): string
    {
        return 'f'.$this->id;
    }
}
