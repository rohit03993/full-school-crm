<?php

use App\Http\Middleware\EnsureAttendanceDisplayToken;
use App\Http\Middleware\EnsureLicenseFeature;
use App\Http\Middleware\EnsureStudentPortalAuth;
use App\Support\CrmLivewireErrors;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require __DIR__.'/../routes/biometric.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'webhooks/meta/whatsapp',
            'api/v1/campaign/t1/api/v2',
            'campaign/t1/api/v2',
            'api/face-verify/approve',
            'api/face-verify/camera-punch',
            'iclock/*',
            'iclock',
        ]);

        $middleware->alias([
            'student.portal' => EnsureStudentPortalAuth::class,
            'license.feature' => EnsureLicenseFeature::class,
            'attendance.display' => EnsureAttendanceDisplayToken::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('crm:cleanup')->dailyAt('03:00');
        $schedule->command('crm:backup')->dailyAt((string) config('crm-backup.schedule_at', '02:15'));
        $schedule->command('crm:process-late-fees')->dailyAt('00:30');
        $schedule->command('crm:send-fee-reminders')->dailyAt('09:00');
        $schedule->command('crm:send-push-followup-digest')->dailyAt('08:30');
        $schedule->command('attendance:process-punches')->everyMinute();
        $schedule->command('attendance:auto-out')->everyMinute();
        $schedule->command('face-verify:sweep')->everyMinute()->withoutOverlapping();
        $schedule->command('crm:process-queue')->everyMinute()->withoutOverlapping();
        $schedule->command('whatsapp:process-pending')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->header('X-Livewire') && ! $request->header('X-Livewire-Navigate')) {
                return null;
            }

            if ($exception instanceof AuthenticationException) {
                return null;
            }

            if ($exception instanceof HttpExceptionInterface) {
                return null;
            }

            $message = CrmLivewireErrors::messageFor($exception);

            if ($request->header('X-Livewire-Navigate')) {
                return response($message, 500);
            }

            return null;
        });
    })->create();
