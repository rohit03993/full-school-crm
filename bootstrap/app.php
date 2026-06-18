<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'student.portal' => \App\Http\Middleware\EnsureStudentPortalAuth::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('crm:cleanup')->dailyAt('03:00');
        $schedule->command('crm:process-late-fees')->dailyAt('00:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
