<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class BulkSendGuard
{
    public function acquire(string $key, int $seconds = 600): bool
    {
        return Cache::lock($this->name($key), $seconds, 'bulk-send')->get();
    }

    public function release(string $key): void
    {
        Cache::lock($this->name($key), 600, 'bulk-send')->release();
    }

    protected function name(string $key): string
    {
        return 'bulk-send:'.$key;
    }
}
