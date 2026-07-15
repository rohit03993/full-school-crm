<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\MetaWhatsAppService;
use App\Services\StudentAuthService;
use App\Services\WhatsAppOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_password_login_still_works_for_students(): void
    {
        Setting::setValue(
            'portal.shared_password_hash',
            app(StudentAuthService::class)->hashPortalPassword('Motion@2026'),
            'portal',
        );

        Student::query()->create([
            'name' => 'Portal Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9811000099',
            'status' => StudentStatus::Enquiry,
            'portal_password' => null,
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9811000099',
            'password' => 'Motion@2026',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertEquals(Student::query()->where('mobile', '9811000099')->value('id'), session('student_portal_id'));
    }

    public function test_student_otp_login_flow(): void
    {
        $this->enableOtpTemplate();

        $student = Student::query()->create([
            'name' => 'OTP Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9811000011',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $meta = Mockery::mock(MetaWhatsAppService::class);
        $meta->shouldReceive('isConfigured')->andReturn(true);
        $meta->shouldReceive('defaultLanguage')->andReturn('en');
        $meta->shouldReceive('sendAuthenticationOtp')
            ->once()
            ->andReturnUsing(function (string $phone, string $otp) {
                Cache::put('test_last_otp', $otp, 60);

                return ['status' => 'success', 'message_id' => 'wamid.test'];
            });
        $this->app->instance(MetaWhatsAppService::class, $meta);

        $this->post(route('portal.login.otp.send'), [
            'mobile' => '9811000011',
        ])->assertRedirect();

        $otp = (string) Cache::get('test_last_otp');
        $this->assertMatchesRegularExpression('/^\d{4}$/', $otp);

        $this->post(route('portal.login.otp.verify'), [
            'mobile' => '9811000011',
            'otp' => $otp,
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertEquals($student->id, session('student_portal_id'));
    }

    public function test_staff_otp_login_flow(): void
    {
        $this->enableOtpTemplate();

        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'mobile' => '9811000022',
            'is_active' => true,
            'is_platform_operator' => false,
        ]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $meta = Mockery::mock(MetaWhatsAppService::class);
        $meta->shouldReceive('isConfigured')->andReturn(true);
        $meta->shouldReceive('defaultLanguage')->andReturn('en');
        $meta->shouldReceive('sendAuthenticationOtp')
            ->once()
            ->andReturnUsing(function (string $phone, string $otp) {
                Cache::put('test_last_staff_otp', $otp, 60);

                return ['status' => 'success', 'message_id' => 'wamid.staff'];
            });
        $this->app->instance(MetaWhatsAppService::class, $meta);

        $this->post(route('staff.otp-login.send'), [
            'mobile' => '9811000022',
        ])->assertRedirect();

        $otp = (string) Cache::get('test_last_staff_otp');

        $this->post(route('staff.otp-login.verify'), [
            'mobile' => '9811000022',
            'otp' => $otp,
        ])->assertRedirect();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_otp_is_exactly_four_digits(): void
    {
        $service = app(WhatsAppOtpService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateOtp');
        $method->setAccessible(true);

        for ($i = 0; $i < 20; $i++) {
            $otp = $method->invoke($service);
            $this->assertMatchesRegularExpression('/^\d{4}$/', $otp);
        }
    }

    protected function enableOtpTemplate(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.otp_template_name', 'login_otp', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.otp_template_language', 'en', 'meta_whatsapp');
    }
}
