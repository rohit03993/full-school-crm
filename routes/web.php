<?php

use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CertificateDownloadController;
use App\Http\Controllers\Admin\ConsolidatedMarksheetDownloadController;
use App\Http\Controllers\Admin\DocumentDownloadController;
use App\Http\Controllers\Admin\GoogleDriveOAuthController;
use App\Http\Controllers\Admin\HomeworkFileController;
use App\Http\Controllers\Admin\IdCardDownloadController;
use App\Http\Controllers\Admin\MarksheetDownloadController;
use App\Http\Controllers\Admin\MetaWhatsAppMediaController;
use App\Http\Controllers\Admin\PaymentProofDownloadController;
use App\Http\Controllers\Admin\ReceiptDownloadController;
use App\Http\Controllers\Api\AisensyCampaignTriggerController;
use App\Http\Controllers\Display\AttendanceDisplayController;
use App\Http\Controllers\PublicHomeworkController;
use App\Http\Controllers\PublicSite\ContactController;
use App\Http\Controllers\PublicSite\CourseController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\IdCardVerifyController;
use App\Http\Controllers\PublicSite\LoginController;
use App\Http\Controllers\Pwa\AppLaunchController;
use App\Http\Controllers\Pwa\ManifestController;
use App\Http\Controllers\Pwa\PushSubscriptionController;
use App\Http\Controllers\Pwa\PwaIconController;
use App\Http\Controllers\Staff\StaffOtpLoginController;
use App\Http\Controllers\StudentPortal\AuthController;
use App\Http\Controllers\StudentPortal\DashboardController;
use App\Http\Controllers\StudentPortal\HomeworkController;
use App\Http\Controllers\StudentPortal\IdCardDownloadController as PortalIdCardDownloadController;
use App\Http\Controllers\StudentPortal\ReceiptDownloadController as PortalReceiptDownloadController;
use App\Http\Controllers\Webhooks\MetaWhatsAppWebhookController;
use App\Http\Middleware\EnsurePortalLicensed;
use App\Http\Middleware\EnsureStudentPortalAuth;
use App\Http\Middleware\EnsureWebsiteLicensed;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/webhooks/meta/whatsapp', MetaWhatsAppWebhookController::class)
    ->name('webhooks.meta.whatsapp');

Route::post('/campaign/t1/api/v2', AisensyCampaignTriggerController::class)
    ->name('api.aisensy.campaign.trigger.legacy');

Route::prefix('pwa')->name('pwa.')->group(function (): void {
    Route::get('/manifest/{context?}', ManifestController::class)
        ->where('context', 'public|portal|admin|app')
        ->name('manifest');
    Route::get('/icon/{size}', PwaIconController::class)
        ->where('size', '192|512')
        ->name('icon');
    Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey'])
        ->name('push.public-key');
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])
        ->middleware('web')
        ->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy'])
        ->middleware('web')
        ->name('push.unsubscribe');
});

Route::get('/app', AppLaunchController::class)->name('pwa.app');

Route::middleware(['web', 'auth'])->prefix('admin')->group(function () {
    Route::get('backups/{filename}/download', BackupDownloadController::class)
        ->where('filename', 'school-crm-full-backup-[\w\-]+\.zip')
        ->name('admin.backups.download');

    Route::get('backups/google/redirect', [GoogleDriveOAuthController::class, 'redirect'])
        ->name('admin.backups.google.redirect');
    Route::get('backups/google/callback', [GoogleDriveOAuthController::class, 'callback'])
        ->name('admin.backups.google.callback');

    Route::get('documents/{document}/download', [DocumentDownloadController::class, 'download'])
        ->name('admin.documents.download');
    Route::get('documents/{document}/preview', [DocumentDownloadController::class, 'preview'])
        ->name('admin.documents.preview');

    Route::get('whatsapp-messages/{message}/media', [MetaWhatsAppMediaController::class, 'show'])
        ->name('admin.whatsapp-messages.media');

    Route::middleware('license.feature:fees')->group(function () {
        Route::get('payments/{payment}/proof/preview', [PaymentProofDownloadController::class, 'preview'])
            ->name('admin.payments.proof.preview');
        Route::get('payments/{payment}/proof/download', [PaymentProofDownloadController::class, 'download'])
            ->name('admin.payments.proof.download');
        Route::get('receipts/{payment}/preview', [ReceiptDownloadController::class, 'preview'])
            ->name('admin.receipts.preview');
        Route::get('receipts/{payment}/download', [ReceiptDownloadController::class, 'download'])
            ->name('admin.receipts.download');
    });

    Route::get('enrollments/{enrollment}/id-card/preview', [IdCardDownloadController::class, 'preview'])
        ->name('admin.id-cards.preview');
    Route::get('enrollments/{enrollment}/id-card/download', [IdCardDownloadController::class, 'download'])
        ->name('admin.id-cards.download');

    Route::middleware('license.feature:homework')->group(function () {
        Route::get('homework-assignments/{homeworkAssignment}/preview', [HomeworkFileController::class, 'preview'])
            ->name('admin.homework.preview');
        Route::get('homework-assignments/{homeworkAssignment}/download', [HomeworkFileController::class, 'download'])
            ->name('admin.homework.download');
    });

    Route::middleware('license.feature:marksheets')->group(function () {
        Route::get('marksheets/{marksheet}/preview', [MarksheetDownloadController::class, 'preview'])
            ->name('admin.marksheets.preview');
        Route::get('marksheets/{marksheet}/download', [MarksheetDownloadController::class, 'download'])
            ->name('admin.marksheets.download');
        Route::get('marksheets/consolidated/download', [ConsolidatedMarksheetDownloadController::class, 'download'])
            ->name('admin.marksheets.consolidated.download');
    });

    Route::middleware('license.feature:certificates')->group(function () {
        Route::get('certificates/{certificate}/preview', [CertificateDownloadController::class, 'preview'])
            ->name('admin.certificates.preview');
        Route::get('certificates/{certificate}/download', [CertificateDownloadController::class, 'download'])
            ->name('admin.certificates.download');
    });
});

Route::get('/verify/{enrollment}', IdCardVerifyController::class)->name('id-card.verify');

Route::middleware(['license.feature:homework', 'throttle:60,1'])->group(function (): void {
    Route::get('/h/{token}', [PublicHomeworkController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{24,64}')
        ->name('homework.public.show');
    Route::get('/h/{token}/view', [PublicHomeworkController::class, 'view'])
        ->where('token', '[A-Za-z0-9]{24,64}')
        ->name('homework.public.view');
    Route::get('/h/{token}/download', [PublicHomeworkController::class, 'download'])
        ->where('token', '[A-Za-z0-9]{24,64}')
        ->name('homework.public.download');
});

Route::get('/display/attendance/photo/{document}', [AttendanceDisplayController::class, 'photo'])
    ->middleware('throttle:120,1')
    ->name('display.attendance.photo');

Route::prefix('display/attendance')
    ->name('display.attendance.')
    ->middleware(['attendance.display'])
    ->group(function (): void {
        Route::get('/{token}', [AttendanceDisplayController::class, 'show'])
            ->name('show');
        Route::get('/{token}/latest', [AttendanceDisplayController::class, 'latest'])
            ->middleware('throttle:120,1')
            ->name('latest');
    });

Route::middleware(EnsureWebsiteLicensed::class)->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/courses', CourseController::class)->name('courses');
    Route::get('/contact', ContactController::class)->name('contact');
});

Route::get('/login', LoginController::class)->name('login');

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/otp-login', [StaffOtpLoginController::class, 'show'])->name('otp-login');
    Route::post('/otp-login/send', [StaffOtpLoginController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('otp-login.send');
    Route::post('/otp-login/verify', [StaffOtpLoginController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('otp-login.verify');
});

Route::post('/contact/enquiry', [ContactController::class, 'store'])
    ->middleware(['throttle:10,1', 'license.feature:enquiries', EnsureWebsiteLicensed::class])
    ->name('contact.enquiry');

Route::prefix('portal')->name('portal.')->middleware(EnsurePortalLicensed::class)->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.submit');
    Route::post('/login/otp/send', [AuthController::class, 'sendLoginOtp'])
        ->middleware('throttle:5,1')
        ->name('login.otp.send');
    Route::post('/login/otp/verify', [AuthController::class, 'verifyLoginOtp'])
        ->middleware('throttle:10,1')
        ->name('login.otp.verify');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(EnsureStudentPortalAuth::class)->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/switch-child', [AuthController::class, 'switchChild'])
            ->middleware('throttle:30,1')
            ->name('switch-child');
        Route::post('/password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:10,1')
            ->name('password.change');
        Route::post('/admission', [DashboardController::class, 'submitAdmission'])->name('admission.submit');
        Route::get('/receipts/{payment}/download', [PortalReceiptDownloadController::class, 'download'])
            ->middleware('license.feature:fees')
            ->name('receipts.download');
        Route::get('/id-card/download', [PortalIdCardDownloadController::class, 'download'])
            ->name('id-card.download');
        Route::middleware('license.feature:homework')->group(function (): void {
            Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
            Route::get('/homework/{homeworkAssignment}', [HomeworkController::class, 'show'])->name('homework.show');
            Route::get('/homework/{homeworkAssignment}/view', [HomeworkController::class, 'view'])->name('homework.view');
            Route::get('/homework/{homeworkAssignment}/download', [HomeworkController::class, 'download'])->name('homework.download');
        });
    });
});
