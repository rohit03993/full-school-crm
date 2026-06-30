<?php

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;

return [

    /*
    | Hidden vendor console path — not linked from the school admin UI.
    | Change on each deployment; keep out of public docs given to schools.
    */
    'platform_panel_path' => env('PLATFORM_PANEL_PATH', '_vendor-console'),

    /*
    | Default license applied on first boot when nothing is stored yet.
    */
    'default_plan' => env('LICENSE_DEFAULT_PLAN', LicensePlan::FullResults->value),

    'default_valid_days' => (int) env('LICENSE_DEFAULT_VALID_DAYS', 365),

    /*
    | School dashboard warnings — shown to Super Admin only.
    */
    'expiry_warning_days' => (int) env('LICENSE_EXPIRY_WARNING_DAYS', 30),

    'expiry_critical_days' => (int) env('LICENSE_EXPIRY_CRITICAL_DAYS', 7),

    /*
    | Preset feature packs — platform admin can switch plan or override with Custom.
    |
    | @var array<string, list<string>>
    */
    'plan_features' => [
        LicensePlan::Starter->value => [
            LicenseFeature::Attendance->value,
            LicenseFeature::Marks->value,
        ],
        LicensePlan::AcademicPlus->value => [
            LicenseFeature::Attendance->value,
            LicenseFeature::Marks->value,
            LicenseFeature::Fees->value,
            LicenseFeature::Homework->value,
        ],
        LicensePlan::FullCrm->value => [
            LicenseFeature::Attendance->value,
            LicenseFeature::Marks->value,
            LicenseFeature::Fees->value,
            LicenseFeature::Homework->value,
            LicenseFeature::Enquiries->value,
            LicenseFeature::Calls->value,
            LicenseFeature::Admissions->value,
            LicenseFeature::WhatsApp->value,
            LicenseFeature::Portal->value,
            LicenseFeature::Reports->value,
            LicenseFeature::Website->value,
        ],
        LicensePlan::FullResults->value => LicenseFeature::values(),
    ],

];
