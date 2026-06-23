<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use Filament\Facades\Filament;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __invoke(): View
    {
        return view('public.login', [
            'staffLoginUrl' => Filament::getPanel('admin')->getLoginUrl(),
            'studentLoginUrl' => route('portal.login'),
        ]);
    }
}
