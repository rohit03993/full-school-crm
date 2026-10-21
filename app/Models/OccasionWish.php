<?php

namespace App\Models;

use App\Enums\OccasionWishStatus;
use App\Enums\OccasionWishType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccasionWish extends Model
{
    protected $fillable = [
        'occasion',
        'occasion_on',
        'recipient_key',
        'student_id',
        'user_id',
        'recipient_name',
        'mobile',
        'status',
        'skip_reason',
        'provider_message_id',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'occasion' => OccasionWishType::class,
            'occasion_on' => 'date',
            'status' => OccasionWishStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
