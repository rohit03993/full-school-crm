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
            'staffLoginUrl' => app(\App\Services\WhatsAppOtpService::class)->passwordLoginAllowed()
                ? Filament::getPanel('admin')->getLoginUrl()
                : route('staff.otp-login'),
            'studentLoginUrl' => route('portal.login'),
            'otpOnly' => ! app(\App\Services\WhatsAppOtpService::class)->passwordLoginAllowed(),
        ]);
    }
}
