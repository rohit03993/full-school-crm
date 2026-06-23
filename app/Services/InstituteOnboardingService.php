<?php

namespace App\Services;

use App\Models\CustomFieldDefinition;
use App\Models\Setting;
use App\Support\InstituteOnboarding;
use App\Support\InstituteTerminology;
use App\Support\SiteContent;
use App\Support\InstituteSettings;

class InstituteOnboardingService
{
    public function __construct(
        protected SiteContentService $siteContent,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(array $data): void
    {
        $this->siteContent->save([
            'name' => $data['name'] ?? '',
            'tagline' => $data['tagline'] ?? '',
            'number_prefix' => $data['number_prefix'] ?? 'CRM',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'whatsapp' => $data['whatsapp'] ?? '',
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'hours' => $data['hours'] ?? '',
            'established' => $data['established'] ?? '',
            'hero_title' => $data['hero_title'] ?? '',
            'hero_subtitle' => $data['hero_subtitle'] ?? '',
            'about' => $data['about'] ?? '',
            'social_facebook' => '',
            'social_instagram' => '',
            'social_youtube' => '',
            'logo' => null,
            'favicon' => null,
            'hero_main_image' => null,
            'hero_accent_one' => null,
            'hero_accent_two' => null,
            'about_image' => null,
            'highlights' => $data['highlights'] ?? $this->siteContent->defaultSiteSettings()['site.highlights']['value'],
            'gallery_items' => [],
            'home_about_eyebrow' => $data['home_about_eyebrow'] ?? 'About Us',
            'home_about_title' => $data['home_about_title'] ?? '',
            'home_about_points' => $data['home_about_points'] ?? [],
            'home_about_cta' => $data['home_about_cta'] ?? 'Learn more about admissions',
            'home_courses_eyebrow' => $data['home_courses_eyebrow'] ?? 'Our Programmes',
            'home_courses_title' => $data['home_courses_title'] ?? '',
            'home_courses_subtitle' => $data['home_courses_subtitle'] ?? '',
            'home_show_courses_section' => $data['home_show_courses_section'] ?? true,
            'home_cta_title' => $data['home_cta_title'] ?? '',
            'home_cta_subtitle' => $data['home_cta_subtitle'] ?? '',
            'hero_stats' => $data['hero_stats'] ?? [],
        ]);

        InstituteTerminology::save([
            'course' => $data['label_course'] ?? '',
            'batch' => $data['label_batch'] ?? '',
            'roll_number' => $data['label_roll_number'] ?? '',
            'programmes_heading' => $data['label_programmes_heading'] ?? '',
        ]);

        InstituteOnboarding::markComplete();

        SiteContent::clearCache();
        InstituteSettings::clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    public function suggestedDefaults(): array
    {
        $terminology = InstituteTerminology::defaults();

        return [
            'name' => 'Your Institute',
            'tagline' => 'School & Coaching Management',
            'number_prefix' => 'CRM',
            'phone' => '',
            'email' => '',
            'whatsapp' => '',
            'address' => '',
            'city' => '',
            'hours' => 'Mon – Sat: 9:00 AM – 6:00 PM',
            'established' => (string) now()->year,
            'hero_title' => 'Quality Education for Every Student',
            'hero_subtitle' => 'Admissions, fees, batches, and attendance — managed in one place for your institute.',
            'about' => 'We are focused on academic excellence and student success. Add your own story here after setup.',
            'label_course' => $terminology['course'],
            'label_batch' => $terminology['batch'],
            'label_roll_number' => $terminology['roll_number'],
            'label_programmes_heading' => $terminology['programmes_heading'],
            'home_about_eyebrow' => 'About Us',
            'home_about_title' => 'Training the next generation of learners',
            'home_about_points' => [
                ['text' => 'Experienced faculty and mentors'],
                ['text' => 'Structured programmes and flexible batches'],
                ['text' => 'Guidance from enquiry to enrollment'],
            ],
            'home_about_cta' => 'Learn more about admissions',
            'home_courses_eyebrow' => 'Our Programmes',
            'home_courses_title' => 'Programmes designed for your students',
            'home_courses_subtitle' => 'Choose the programme that fits your goals.',
            'home_show_courses_section' => true,
            'home_cta_title' => 'Ready to start your learning journey?',
            'home_cta_subtitle' => 'Visit our campus, speak with our counsellors, or call us to learn more about admissions.',
            'hero_stats' => [
                ['title' => 'Programmes', 'subtitle' => 'Courses on offer'],
                ['title' => 'Batches', 'subtitle' => 'Flexible groups'],
                ['title' => '100%', 'subtitle' => 'Student focus'],
            ],
            'highlights' => [
                ['value' => '15+', 'label' => 'Years of Excellence'],
                ['value' => '500+', 'label' => 'Students Enrolled'],
                ['value' => '100%', 'label' => 'Dedicated Faculty'],
                ['value' => '10+', 'label' => 'Programme Options'],
            ],
        ];
    }
}
