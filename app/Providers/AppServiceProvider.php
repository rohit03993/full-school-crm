<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use App\Support\SiteContent;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        View::composer([
            'layouts.public',
            'public.*',
            'components.public.*',
            'portal.*',
        ], function ($view): void {
            $view->with('institute', SiteContent::institute());
        });
    }
}
