<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->validateCsrfTokens(except: [
            'webhooks/meta/whatsapp',
            'api/v1/campaign/t1/api/v2',
            'campaign/t1/api/v2',
        ]);

        $middleware->alias([
            'student.portal' => \App\Http\Middleware\EnsureStudentPortalAuth::class,
            'license.feature' => \App\Http\Middleware\EnsureLicenseFeature::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('crm:cleanup')->dailyAt('03:00');
        $schedule->command('crm:backup')->dailyAt((string) config('crm-backup.schedule_at', '02:15'));
        $schedule->command('crm:process-late-fees')->dailyAt('00:30');
        $schedule->command('crm:send-fee-reminders')->dailyAt('09:00');
        $schedule->command('attendance:process-punches')->everyMinute();
        $schedule->command('attendance:auto-out')->everyFiveMinutes();
        $schedule->command('crm:process-queue')->everyMinute()->withoutOverlapping();
        $schedule->command('whatsapp:process-pending')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $exception, \Illuminate\Http\Request $request) {
            if (! $request->header('X-Livewire') && ! $request->header('X-Livewire-Navigate')) {
                return null;
            }

            if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
                return null;
            }

            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null;
            }

            $message = \App\Support\CrmLivewireErrors::messageFor($exception);

            if ($request->header('X-Livewire-Navigate')) {
                return response($message, 500);
            }

            return null;
        });
    })->create();
