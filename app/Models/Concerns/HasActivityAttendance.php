<?php

namespace App\Models\Concerns;

use App\Models\ActivityAttendance;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasActivityAttendance
{
    public function activityAttendances(): MorphMany
    {
        return $this->morphMany(ActivityAttendance::class, 'attendable');
    }

    public function presentCount(): int
    {
        return $this->activityAttendances()->where('is_present', true)->count();
    }
}
