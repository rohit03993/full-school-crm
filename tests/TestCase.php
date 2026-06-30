<?php

namespace Tests;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushValueCache();

        if (Schema::hasTable('permissions')) {
            app(CrmPermissionSyncService::class)->sync();
        }
    }

    protected function createTrainerUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['is_active' => true], $attributes));
    }

    protected function createBatchForCourse(Course $course, array $attributes = []): Batch
    {
        $trainerId = $attributes['trainer_user_id'] ?? $this->createTrainerUser()->id;
        unset($attributes['trainer_user_id']);

        return Batch::query()->create(array_merge([
            'name' => 'Test Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $trainerId,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => BatchStatus::Active,
        ], $attributes));
    }
}
