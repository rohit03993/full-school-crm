<?php

namespace Tests\Feature;

use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BiometricAdmsIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_handshake_returns_options_for_allowlisted_device(): void
    {
        BiometricDevice::query()->create([
            'name' => 'Reception',
            'serial_number' => 'K40TEST001',
            'is_active' => true,
        ]);

        $response = $this->get('/iclock/cdata?SN=K40TEST001&options=all');

        $response->assertOk();
        $response->assertSee('GET OPTION FROM: K40TEST001', false);
        $response->assertSee('Realtime=1', false);
        // K40 expects minutes east of UTC (IST = 330), not Asia/Kolkata.
        $response->assertSee('TimeZone=330', false);
        $response->assertSee('DateTime=', false);
    }

    public function test_attlog_is_stored_and_mirrored_to_punch_logs(): void
    {
        BiometricDevice::query()->create([
            'name' => 'Reception',
            'serial_number' => 'K40TEST001',
            'location' => 'Gate',
            'is_active' => true,
        ]);

        $body = "78979877\t2026-07-11 10:15:00\t0\t1\t0";

        $response = $this->call(
            'POST',
            '/iclock/cdata?SN=K40TEST001&table=ATTLOG&Stamp=2026-07-11T10:15:00',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body,
        );

        $response->assertOk();
        $response->assertSee('OK', false);

        $this->assertDatabaseHas('biometric_punches', [
            'serial_number' => 'K40TEST001',
            'user_pin' => '78979877',
            'process_status' => BiometricPunch::STATUS_MIRRORED,
        ]);

        $this->assertTrue(
            DB::table('punch_logs')
                ->where('employee_id', '78979877')
                ->where('punch_date', '2026-07-11')
                ->where('punch_time', '10:15:00')
                ->exists()
        );
    }

    public function test_unknown_serial_does_not_store_punches(): void
    {
        $body = "78979877\t2026-07-11 10:15:00\t0\t1\t0";

        $response = $this->call(
            'POST',
            '/iclock/cdata?SN=UNKNOWN999&table=ATTLOG&Stamp=1',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body,
        );

        $response->assertOk();
        $this->assertSame(0, BiometricPunch::query()->count());
        $this->assertSame(0, DB::table('punch_logs')->count());
    }

    public function test_getrequest_returns_ok(): void
    {
        BiometricDevice::query()->create([
            'name' => 'Reception',
            'serial_number' => 'K40TEST001',
            'is_active' => true,
        ]);

        $this->get('/iclock/getrequest?SN=K40TEST001')->assertOk()->assertSee('OK', false);
    }
}
