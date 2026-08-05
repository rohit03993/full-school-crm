<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\FaceVerificationRequest;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\FaceVerify\FaceVerifyClient;
use App\Services\FaceVerify\FaceVerifyGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffFaceVerifySyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);

        if (! Schema::hasTable('punch_logs')) {
            Schema::create('punch_logs', function ($table) {
                $table->id();
                $table->string('employee_id', 64);
                $table->date('punch_date');
                $table->time('punch_time');
                $table->string('device_name')->nullable();
                $table->string('area_name')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
            });
        }

        config([
            'face_verify.enabled' => true,
            'face_verify.api_url' => 'https://face-api.test',
            'face_verify.service_token' => 'crm-service-token',
            'face_verify.callback_secret' => 'crm-callback-secret',
        ]);
    }

    public function test_staff_upsert_payload_uses_employee_code(): void
    {
        $user = $this->createStaff('STF900');

        Http::fake([
            'https://face-api.test/students' => Http::response(['ok' => true], 200),
        ]);

        app(FaceVerifyClient::class)->upsertStaff($user);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://face-api.test/students'
                && ($data['enrollment_number'] ?? null) === 'STF900'
                && ($data['subject'] ?? null) === 'staff'
                && ($data['crm_user_id'] ?? null) === (string) User::query()->whereHas('staffProfile', fn ($q) => $q->where('employee_code', 'STF900'))->value('id');
        });
    }

    public function test_camera_punch_accepts_staff_id(): void
    {
        $user = $this->createStaff('STF901');

        $result = app(FaceVerifyGateService::class)->recordCameraPunch([
            'enrollment_number' => 'STF901',
            'score' => 0.99,
            'device_id' => '',
            'timestamp' => '2026-08-05T09:30:00+05:30',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['already_processed'] ?? false);

        $request = FaceVerificationRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame('staff', $request->subject);
        $this->assertSame($user->id, $request->user_id);
        $this->assertNull($request->student_id);
    }

    protected function createStaff(string $code): User
    {
        $user = User::factory()->create([
            'name' => 'Face Staff',
            'mobile' => '9876509999',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);
        StaffProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'designation' => 'Teacher',
            'mobile' => $user->mobile,
        ]);

        return $user->fresh('staffProfile');
    }
}
