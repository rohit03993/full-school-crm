<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use App\Services\CustomFieldService;
use App\Services\InstituteOnboardingService;
use App\Support\InstituteOnboarding;
use App\Support\InstituteTerminology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstituteOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_install_requires_onboarding(): void
    {
        Setting::setValue('site.name', 'Your Institute', 'general');

        $this->assertFalse(InstituteOnboarding::isComplete());
    }

    public function test_onboarding_service_marks_complete_and_saves_settings(): void
    {
        $admin = $this->createSuperAdmin();

        app(InstituteOnboardingService::class)->complete([
            'name' => 'Apex Coaching',
            'tagline' => 'Best results',
            'number_prefix' => 'APEX',
            'phone' => '9876543210',
            'email' => 'info@apex.test',
            'whatsapp' => '',
            'address' => 'Main Road',
            'city' => 'Delhi',
            'hours' => '9-5',
            'established' => '2015',
            'hero_title' => 'Crack the exam',
            'hero_subtitle' => 'Expert faculty',
            'about' => 'About apex',
            'label_course' => 'Programme',
            'label_batch' => 'Batch',
            'label_roll_number' => 'Reg. No.',
            'label_programmes_heading' => 'Our Courses',
            'home_show_courses_section' => true,
            'hero_stats' => [['title' => 'JEE', 'subtitle' => 'Coaching']],
            'highlights' => [['value' => '10+', 'label' => 'Years']],
        ]);

        $this->assertTrue(InstituteOnboarding::isComplete());
        $this->assertSame('Apex Coaching', Setting::getValue('site.name'));
        $this->assertSame('Programme', InstituteTerminology::label('course'));
        $this->assertSame('Reg. No.', InstituteTerminology::label('roll_number'));
        $this->assertTrue((bool) Setting::getValue('site.home_show_courses_section'));
    }

    public function test_custom_field_definitions_sync_for_student(): void
    {
        app(CustomFieldService::class)->syncDefinitions(CustomFieldService::ENTITY_STUDENT, [
            [
                'label' => 'Blood Group',
                'field_type' => 'select',
                'options' => [['value' => 'A+'], ['value' => 'B+']],
                'is_required' => false,
                'is_active' => true,
            ],
        ]);

        $definitions = app(CustomFieldService::class)->activeDefinitions(CustomFieldService::ENTITY_STUDENT);

        $this->assertCount(1, $definitions);
        $this->assertSame('blood_group', $definitions[0]->field_key);
    }

    public function test_custom_field_definitions_sync_for_enquiry(): void
    {
        app(CustomFieldService::class)->syncDefinitions(CustomFieldService::ENTITY_ENQUIRY, [
            [
                'label' => 'Referral source',
                'field_type' => 'text',
                'is_required' => false,
                'is_active' => true,
            ],
        ]);

        $definitions = app(CustomFieldService::class)->activeDefinitions(CustomFieldService::ENTITY_ENQUIRY);

        $this->assertCount(1, $definitions);
        $this->assertSame('referral_source', $definitions[0]->field_key);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        return $admin;
    }
}
