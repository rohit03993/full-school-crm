<?php

namespace App\Models\Concerns;

use App\Services\StudentRecordGuard;

trait BlocksInactiveStudentWrites
{
    public static function bootBlocksInactiveStudentWrites(): void
    {
        $guard = function (self $model): void {
            app(StudentRecordGuard::class)->assertModelWritable($model);
        };

        static::creating($guard);
        static::updating($guard);
        static::deleting($guard);
    }
}
