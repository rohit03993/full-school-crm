<?php

use App\Http\Controllers\Biometric\AdmsIclockController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('biometric.route_prefix', 'iclock'), '/');

Route::prefix($prefix)->group(function (): void {
    Route::match(['get', 'post'], 'cdata', [AdmsIclockController::class, 'cdata'])
        ->name('biometric.adms.cdata');
    Route::match(['get', 'post'], 'getrequest', [AdmsIclockController::class, 'getrequest'])
        ->name('biometric.adms.getrequest');
    Route::match(['get', 'post'], 'devicecmd', [AdmsIclockController::class, 'devicecmd'])
        ->name('biometric.adms.devicecmd');
    Route::match(['get', 'post'], 'registry', [AdmsIclockController::class, 'registry'])
        ->name('biometric.adms.registry');
});
