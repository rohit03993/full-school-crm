<?php

use App\Http\Controllers\Biometric\AdmsIclockController;
use Illuminate\Support\Facades\Route;

$prefix = trim((string) config('biometric.route_prefix', 'iclock'), '/');

Route::prefix($prefix)->group(function (): void {
    // K40 / classic ADMS uses /iclock/cdata. eSSL Push 2.x uses /iclock/cdata.aspx.
    foreach (['cdata', 'getrequest', 'devicecmd', 'registry'] as $page) {
        Route::match(['get', 'post'], $page, [AdmsIclockController::class, $page])
            ->name('biometric.adms.'.$page);
        Route::match(['get', 'post'], $page.'.aspx', [AdmsIclockController::class, $page])
            ->name('biometric.adms.'.$page.'.aspx');
    }
});
