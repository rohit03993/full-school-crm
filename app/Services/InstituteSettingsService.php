<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class InstituteSettingsService
{
    public function __construct(
        protected StudentAuthService $studentAuth,
    ) {}

    public function getFormData(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('institute.name')),
            'tagline' => $g('site.tagline', config('institute.tagline')),
            'phone' => $g('site.phone', config('institute.phone')),
            'email' => $g('site.email', config('institute.email')),
            'address' => $g('site.address', config('institute.address')),
            'receipt_logo' => $g('crm.receipt_logo') ?: $g('site.logo'),
            'receipt_header' => $g('crm.receipt_header', ''),
            'receipt_footer' => $g('crm.receipt_footer', config('institute.receipt_footer')),
            'id_card_primary_color' => InstituteSettings::normalizeHexColor($g('crm.id_card_primary_color'), '#1e40af'),
            'id_card_accent_color' => InstituteSettings::normalizeHexColor($g('crm.id_card_accent_color'), '#dc2626'),
            'id_card_badge_color' => InstituteSettings::normalizeHexColor($g('crm.id_card_badge_color'), '#fbbf24'),
            'portal_shared_password' => '',
            'marksheet_title' => $g('marksheet.title', 'Statement of Marks'),
            'marksheet_footer_note' => $g('marksheet.footer_note', 'This is a computer-generated marksheet. Collect the official signed copy from the institute office if required.'),
            'marksheet_signature_title' => $g('marksheet.signature_title', 'Controller of Examination'),
            'marksheet_signature_name' => $g('marksheet.signature_name', ''),
            'marksheet_show_rank' => filter_var($g('marksheet.show_rank', true), FILTER_VALIDATE_BOOLEAN),
            'marksheet_show_attendance' => filter_var($g('marksheet.show_attendance', true), FILTER_VALIDATE_BOOLEAN),
            'marksheet_show_subject_remarks' => filter_var($g('marksheet.show_subject_remarks', false), FILTER_VALIDATE_BOOLEAN),
            'marksheet_show_principal_remarks' => filter_var($g('marksheet.show_principal_remarks', true), FILTER_VALIDATE_BOOLEAN),
            'marksheet_division_first' => (float) $g('marksheet.division_first', 55),
            'marksheet_division_second' => (float) $g('marksheet.division_second', 48),
            'marksheet_division_pass' => (float) $g('marksheet.division_pass', 40),
        ];
    }

    public function save(array $data): void
    {
        $this->persistImage('crm.receipt_logo', $data['receipt_logo'] ?? null);

        Setting::setValue('crm.receipt_header', $data['receipt_header'] ?? '', 'crm');
        Setting::setValue('crm.receipt_footer', $data['receipt_footer'] ?? '', 'crm');
        Setting::setValue(
            'crm.id_card_primary_color',
            InstituteSettings::normalizeHexColor($data['id_card_primary_color'] ?? null, '#1e40af'),
            'crm',
        );
        Setting::setValue(
            'crm.id_card_accent_color',
            InstituteSettings::normalizeHexColor($data['id_card_accent_color'] ?? null, '#dc2626'),
            'crm',
        );
        Setting::setValue(
            'crm.id_card_badge_color',
            InstituteSettings::normalizeHexColor($data['id_card_badge_color'] ?? null, '#fbbf24'),
            'crm',
        );

        Setting::setValue('marksheet.title', $data['marksheet_title'] ?? 'Statement of Marks', 'marksheet');
        Setting::setValue('marksheet.footer_note', $data['marksheet_footer_note'] ?? '', 'marksheet');
        Setting::setValue('marksheet.signature_title', $data['marksheet_signature_title'] ?? '', 'marksheet');
        Setting::setValue('marksheet.signature_name', $data['marksheet_signature_name'] ?? '', 'marksheet');
        Setting::setValue('marksheet.show_rank', ($data['marksheet_show_rank'] ?? true) ? '1' : '0', 'marksheet');
        Setting::setValue('marksheet.show_attendance', ($data['marksheet_show_attendance'] ?? true) ? '1' : '0', 'marksheet');
        Setting::setValue('marksheet.show_subject_remarks', ($data['marksheet_show_subject_remarks'] ?? false) ? '1' : '0', 'marksheet');
        Setting::setValue('marksheet.show_principal_remarks', ($data['marksheet_show_principal_remarks'] ?? true) ? '1' : '0', 'marksheet');
        Setting::setValue('marksheet.division_first', (string) ($data['marksheet_division_first'] ?? 55), 'marksheet');
        Setting::setValue('marksheet.division_second', (string) ($data['marksheet_division_second'] ?? 48), 'marksheet');
        Setting::setValue('marksheet.division_pass', (string) ($data['marksheet_division_pass'] ?? 40), 'marksheet');

        Setting::setValue('portal.login_mode', StudentAuthService::LOGIN_MODE_SHARED, 'portal');

        $sharedPlain = trim((string) ($data['portal_shared_password'] ?? ''));

        if ($sharedPlain !== '') {
            Setting::setValue(
                'portal.shared_password_hash',
                $this->studentAuth->hashPortalPassword($sharedPlain),
                'portal',
            );
        } else {
            $this->studentAuth->sharedPortalPasswordHash();
        }

        InstituteSettings::clearCache();
        SiteContent::clearCache();
    }

    protected function persistImage(string $key, mixed $newState): void
    {
        $oldPath = Setting::getValue($key);
        $newPath = SiteImageService::normalizePath($newState);

        SiteImageService::replace($oldPath, $newPath);
        Setting::setValue($key, $newPath ?? '', 'crm');
    }
}
