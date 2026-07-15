<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppOtpService;
use App\Support\IndianMobileNumber;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StaffOtpLoginController extends Controller
{
    public function show(WhatsAppOtpService $otp): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Filament::getPanel('admin')->getUrl());
        }

        return view('staff.otp-login', [
            'otpAvailable' => $otp->isAvailable(),
            'otpSent' => (bool) session('otp_sent'),
            'otpMobile' => session('otp_mobile', old('mobile')),
            'passwordLoginUrl' => Filament::getPanel('admin')->getLoginUrl(),
        ]);
    }

    public function send(Request $request, WhatsAppOtpService $otp): RedirectResponse
    {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->withInput($request->only('mobile'))
                ->withErrors(['mobile' => 'Enter a valid 10-digit mobile number (with or without +91).']);
        }

        $request->merge(['mobile' => $mobile]);

        $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ]);

        $user = $this->findActiveStaffByMobile($mobile);

        if (! $user) {
            return back()
                ->withInput($request->only('mobile'))
                ->withErrors(['mobile' => 'This mobile number is not registered for staff login.']);
        }

        if ($user->isPlatformOperator()) {
            return back()
                ->withInput($request->only('mobile'))
                ->withErrors(['mobile' => 'Use the vendor console to sign in as platform operator.']);
        }

        $result = $otp->send($mobile, WhatsAppOtpService::PURPOSE_STAFF, $user->name);

        if (! $result['success']) {
            return back()
                ->withInput($request->only('mobile'))
                ->withErrors(['mobile' => $result['message']]);
        }

        return back()
            ->with('otp_sent', true)
            ->with('otp_mobile', $mobile)
            ->with('otp_success', $result['message'].' Enter the 4-digit code below.');
    }

    public function verify(Request $request, WhatsAppOtpService $otp): RedirectResponse
    {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->with('otp_sent', true)
                ->withErrors(['mobile' => 'Enter a valid 10-digit mobile number.']);
        }

        $request->merge(['mobile' => $mobile]);

        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'otp' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'otp.size' => 'OTP must be 4 digits.',
            'otp.regex' => 'OTP must be 4 digits.',
        ]);

        if (! $otp->verify($data['mobile'], WhatsAppOtpService::PURPOSE_STAFF, $data['otp'])) {
            return back()
                ->withInput($request->only('mobile', 'remember'))
                ->with('otp_sent', true)
                ->with('otp_mobile', $mobile)
                ->withErrors(['otp' => 'Invalid or expired OTP. Please request a new one.']);
        }

        $user = $this->findActiveStaffByMobile($data['mobile']);

        if (! $user || $user->isPlatformOperator()) {
            return back()
                ->with('otp_sent', true)
                ->withErrors(['mobile' => 'This mobile number is not registered for staff login.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(Filament::getPanel('admin')->getUrl());
    }

    protected function findActiveStaffByMobile(string $mobile): ?User
    {
        return User::query()
            ->where('mobile', $mobile)
            ->where('is_active', true)
            ->first();
    }
}
