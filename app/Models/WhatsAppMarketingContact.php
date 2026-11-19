<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMarketingContact extends Model
{
    protected $table = 'whatsapp_marketing_contacts';

    protected $fillable = [
        'name',
        'mobile',
        'extra',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
        ];
    }
}
