<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id',
        'designation',
        'employee_code',
        'mobile',
        'date_of_birth',
        'marriage_anniversary',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'marriage_anniversary' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
