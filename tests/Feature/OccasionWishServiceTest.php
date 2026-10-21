<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\OccasionWishStatus;
use App\Enums\OccasionWishType;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\ManageWhatsAppSettings;
use App\Filament\Pages\OccasionWishesPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\OccasionWish;
use App\Models\Setting;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\LicenseService;
use App\Services\MetaWhatsAppMessageLogger;
use App\Services\OccasionWishService;
use App\Services\WhatsAppDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OccasionWishServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_today_list_includes_enrolled_student_and_active_staff_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 07:00:00'));

        $this->mock(WhatsAppDispatchService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $student = $this->makeStudent('Aarav Sharma', '2020-10-21', '9811111111', StudentStatus::Enrolled);
        $this->makeStudent('Old Student', '2019-10-21', '9822222222', StudentStatus::Dropped);
        $this->makeStudent('Tomorrow Student', '2018-10-22', '9833333333', StudentStatus::Enrolled);
        $this->makeStudent('No Mobile', '2017-10-21', '', StudentStatus::Enrolled);

        $staff = $this->makeStaff('Hari Sir', '9876543210', true, '1980-10-21', '2010-10-21');
        $this->makeStaff('Inactive Sir', '9876543211', false, '1981-10-21', null);

        $result = app(OccasionWishService::class)->runScheduled();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(4, OccasionWish::query()->count());
        $this->assertDatabaseHas('occasion_wishes', [
            'recipient_key' => 'student:'.$student->id,
            'status' => OccasionWishStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('occasion_wishes', [
            'user_id' => $staff->id,
            'occasion' => OccasionWishType::StaffBirthday->value,
            'status' => OccasionWishStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('occasion_wishes', [
            'user_id' => $staff->id,
            'occasion' => OccasionWishType::StaffAnniversary->value,
            'status' => OccasionWishStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('occasion_wishes', [
            'recipient_name' => 'No Mobile',
            'status' => OccasionWishStatus::Skipped->value,
        ]);

        app(OccasionWishService::class)->syncForDate(now());

        $this->assertSame(4, OccasionWish::query()->count());
    }

    public function test_february_29_is_wished_on_february_28_only_in_a_common_year(): void
    {
        $student = $this->makeStudent('Leap Child', '2016-02-29', '9844444444', StudentStatus::Enrolled);

        Carbon::setTestNow(Carbon::parse('2027-02-28 08:00:00'));
        app(OccasionWishService::class)->syncForDate(now());
        $this->assertTrue($this->wishExistsOn($student->id, '2027-02-28'));

        Carbon::setTestNow(Carbon::parse('2028-02-28 08:00:00'));
        app(OccasionWishService::class)->syncForDate(now());
        $this->assertFalse($this->wishExistsOn($student->id, '2028-02-28'));

        Carbon::setTestNow(Carbon::parse('2028-02-29 08:00:00'));
        app(OccasionWishService::class)->syncForDate(now());
        $this->assertTrue($this->wishExistsOn($student->id, '2028-02-29'));
    }

    public function test_switch_on_sends_each_wish_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00'));
        $this->makeStudent('Aarav Sharma', '2020-10-21', '9811111111', StudentStatus::Enrolled);
        $this->makeStaff('Hari Sir', '9876543210', true, '1980-10-21', '2010-10-21');
        $this->enableWishes();

        $this->mock(WhatsAppDispatchService::class, function ($mock): void {
            $mock->shouldReceive('send')->times(3)->andReturn([
                'status' => 'success',
                'message_id' => 'wamid.wish',
            ]);
        });

        $first = app(OccasionWishService::class)->runScheduled();
        $second = app(OccasionWishService::class)->runScheduled();

        $this->assertSame(3, $first['sent']);
        $this->assertSame(0, $second['sent']);
        $this->assertSame(3, OccasionWish::query()->where('status', OccasionWishStatus::Sent->value)->count());
    }

    public function test_failed_wish_stops_after_the_retry_limit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00'));
        $this->makeStudent('Aarav Sharma', '2020-10-21', '9811111111', StudentStatus::Enrolled);
        $this->enableWishes();
        Setting::setValue(OccasionWishService::SETTING_MAX_RETRIES, '3', 'whatsapp');

        $this->mock(WhatsAppDispatchService::class, function ($mock): void {
            $mock->shouldReceive('send')->times(3)->andReturn([
                'status' => 'failed',
                'error' => 'Meta down',
            ]);
        });

        $service = app(OccasionWishService::class);
        $service->runScheduled();
        $service->runScheduled();
        $service->runScheduled();
        $service->runScheduled();

        $wish = OccasionWish::query()->where('occasion', OccasionWishType::StudentBirthday->value)->first();

        $this->assertNotNull($wish);
        $this->assertSame(OccasionWishStatus::Failed, $wish->status);
        $this->assertSame(3, $wish->attempts);
        $this->assertSame('Meta down', $wish->last_error);
    }

    public function test_delivery_webhook_marks_the_wish_delivered(): void
    {
        $wish = OccasionWish::query()->create([
            'occasion' => OccasionWishType::StudentBirthday->value,
            'occasion_on' => '2026-10-21',
            'recipient_key' => 'student:1',
            'recipient_name' => 'Aarav Sharma',
            'mobile' => '9811111111',
            'status' => OccasionWishStatus::Sent->value,
            'provider_message_id' => 'wamid.delivered',
            'attempts' => 1,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.delivered',
            'direction' => 'outbound',
            'phone' => '919811111111',
            'status' => MetaWhatsAppMessageStatus::Sent->value,
        ]);

        app(MetaWhatsAppMessageLogger::class)->applyWebhookStatus(
            'wamid.delivered',
            MetaWhatsAppMessageStatus::Delivered,
        );

        $this->assertSame(OccasionWishStatus::Delivered, $wish->fresh()->status);
    }

    public function test_wishes_page_and_birthdays_tab_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 07:00:00'));
        Role::findOrCreate(RoleName::SuperAdmin->value);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);

        $this->makeStudent('Aarav Sharma', '2020-10-21', '9811111111', StudentStatus::Enrolled);

        Livewire::test(OccasionWishesPage::class)
            ->assertOk()
            ->assertSee('Aarav Sharma')
            ->assertSee('Student birthday')
            ->assertSee('Wishes are');

        Livewire::test(ManageWhatsAppSettings::class)
            ->assertOk()
            ->assertSee('Birthdays')
            ->assertSee('Student birthday template');
    }

    public function test_whatsapp_off_does_not_build_or_send(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00'));
        $this->makeStudent('Aarav Sharma', '2020-10-21', '9811111111', StudentStatus::Enrolled);
        $this->enableWishes();

        Setting::query()->whereIn('key', [
            LicenseService::PAYLOAD_KEY,
            LicenseService::SIGNATURE_KEY,
        ])->delete();
        Setting::flushValueCache();
        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::Attendance->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->mock(WhatsAppDispatchService::class, function ($mock): void {
            $mock->shouldReceive('send')->never();
        });

        $result = app(OccasionWishService::class)->runScheduled();

        $this->assertSame(0, OccasionWish::query()->count());
        $this->assertStringContainsString('WhatsApp is off', $result['message']);
    }

    private function wishExistsOn(int $studentId, string $day): bool
    {
        return OccasionWish::query()
            ->where('recipient_key', 'student:'.$studentId)
            ->whereDate('occasion_on', $day)
            ->exists();
    }

    private function enableWishes(): void
    {
        Setting::setValue(OccasionWishService::SETTING_ENABLED, '1', 'whatsapp');
        Setting::setValue(OccasionWishService::SETTING_SEND_TIME, '08:00', 'whatsapp');
        Setting::setValue(OccasionWishService::SETTING_REPORT_TIME, '23:59', 'whatsapp');
        Setting::setValue(OccasionWishService::SETTING_MAX_RETRIES, '3', 'whatsapp');

        foreach ([
            OccasionWishService::SETTING_STUDENT_BIRTHDAY_TEMPLATE => 'student_birthday',
            OccasionWishService::SETTING_STAFF_BIRTHDAY_TEMPLATE => 'staff_birthday',
            OccasionWishService::SETTING_STAFF_ANNIVERSARY_TEMPLATE => 'staff_anniversary',
        ] as $key => $name) {
            $template = WhatsAppTemplate::query()->create([
                'name' => $name,
                'param_count' => 2,
                'is_active' => true,
                'body' => 'Hello {{1}} from {{2}}.',
            ]);
            Setting::setValue($key, 'template:'.$template->id, 'whatsapp');
        }
    }

    private function makeStudent(string $name, string $dob, string $mobile, StudentStatus $status): Student
    {
        return Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => $dob,
            'gender' => Gender::Male,
            'mobile' => $mobile !== '' ? $mobile : null,
            'status' => $status,
        ]);
    }

    private function makeStaff(string $name, string $mobile, bool $active, ?string $dob, ?string $anniversary): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'mobile' => $mobile,
            'is_active' => $active,
            'is_platform_operator' => false,
        ]);

        StaffProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'STF'.$user->id,
            'mobile' => $mobile,
            'date_of_birth' => $dob,
            'marriage_anniversary' => $anniversary,
        ]);

        return $user;
    }
}
