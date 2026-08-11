<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Http\Controllers\Controller;
use App\Models\StudentCertificate;
use App\Services\CertificateService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController extends Controller
{
    public function preview(StudentCertificate $certificate): StreamedResponse
    {
        $this->authorizeAccess();
        abort_unless($certificate->hasPdf(), 404);
        abort_unless(Storage::disk(CertificateService::DISK)->exists($certificate->pdf_path), 404);

        $response = Storage::disk(CertificateService::DISK)->response(
            $certificate->pdf_path,
            $this->filename($certificate),
            ['Content-Type' => 'application/pdf'],
        );

        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    public function download(StudentCertificate $certificate): StreamedResponse
    {
        $this->authorizeAccess();
        abort_unless($certificate->hasPdf(), 404);
        abort_unless(Storage::disk(CertificateService::DISK)->exists($certificate->pdf_path), 404);

        return Storage::disk(CertificateService::DISK)->download(
            $certificate->pdf_path,
            $this->filename($certificate),
        );
    }

    protected function authorizeAccess(): void
    {
        abort_unless(auth()->check(), 403);
        abort_unless(FeatureGate::enabled(LicenseFeature::Certificates), 403);
        abort_unless(CrmAccess::can(auth()->user(), CrmPermission::CertificatesView), 403);
    }

    protected function filename(StudentCertificate $certificate): string
    {
        $certificate->loadMissing('student');

        $name = str($certificate->student?->name ?? 'student')->slug('-');
        $type = $certificate->type?->value ?? 'certificate';

        return "{$type}-{$name}-{$certificate->serial_number}.pdf";
    }
}
