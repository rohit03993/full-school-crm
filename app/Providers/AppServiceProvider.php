<?php

namespace App\Providers;

use App\Support\SiteContent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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
