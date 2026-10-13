<?php

namespace App\Models;

use App\Enums\WhatsAppInchatAudience;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppInchatForm extends Model
{
    protected $table = 'whatsapp_inchat_forms';
    protected $fillable = [
        'name',
        'audience',
        'trigger_word',
        'button_label',
        'enabled',
        'status',
        'flow_id',
        'flow_token',
        'needs_republish',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => WhatsAppInchatAudience::class,
            'enabled' => 'boolean',
            'needs_republish' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (WhatsAppInchatForm $form): void {
            if (filled($form->flow_token)) {
                return;
            }

            $form->forceFill([
                'flow_token' => 'form_'.$form->id,
            ])->saveQuietly();
        });
    }

    public function fields(): HasMany
    {
        return $this->hasMany(WhatsAppInchatFormField::class, 'whatsapp_inchat_form_id')->orderBy('sort')->orderBy('id');
    }

    public function stateLabel(): string
    {
        if ($this->status !== 'published') {
            return 'Draft';
        }

        if ($this->needs_republish) {
            return 'Publish again';
        }

        return 'Published';
    }
}
