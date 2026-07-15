<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Services\StudentAuthService;
use App\Services\WhatsAppOtpService;
use App\Support\IndianMobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    use ResolvesPortalStudent;

    public function showLogin(StudentAuthService $auth, WhatsAppOtpService $otp): View|RedirectResponse
    {
        if (session()->has('student_portal_id')) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.login', [
            'loginHint' => $auth->portalLoginHint(),
            'otpAvailable' => $otp->isAvailable(),
            'otpSent' => (bool) session('otp_sent'),
            'otpMobile' => session('otp_mobile', old('mobile')),
            'loginTab' => session('login_tab', request('tab', 'password')),
        ]);
    }

    public function login(Request $request, StudentAuthService $auth): RedirectResponse
    {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'password')
                ->withErrors(['mobile' => 'Enter a valid 10-digit mobile number (with or without +91).']);
        }

        $request->merge(['mobile' => $mobile]);

        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'password' => ['required', 'string', 'min:4', 'max:64'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ]);

        $student = $auth->login($data['mobile'], $data['password']);

        if (! $student) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'password')
                ->withErrors(['mobile' => 'Invalid mobile number or password.']);
        }

        return $this->completePortalLogin($request, $student->id);
    }

    public function sendLoginOtp(
        Request $request,
        StudentAuthService $auth,
        WhatsAppOtpService $otp,
    ): RedirectResponse {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'otp')
                ->withErrors(['mobile' => 'Enter a valid 10-digit mobile number (with or without +91).']);
        }

        $request->merge(['mobile' => $mobile]);

        $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
        ]);

        $student = $auth->findStudentByMobile($mobile);

        if (! $student) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'otp')
                ->withErrors(['mobile' => 'This mobile number is not registered on the student portal.']);
        }

        $result = $otp->send($mobile, WhatsAppOtpService::PURPOSE_STUDENT, $student->name);

        if (! $result['success']) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'otp')
                ->withErrors(['mobile' => $result['message']]);
        }

        return back()
            ->with('login_tab', 'otp')
            ->with('otp_sent', true)
            ->with('otp_mobile', $mobile)
            ->with('otp_success', $result['message'].' Enter the 4-digit code below.');
    }

    public function verifyLoginOtp(
        Request $request,
        StudentAuthService $auth,
        WhatsAppOtpService $otp,
    ): RedirectResponse {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->with('login_tab', 'otp')
                ->with('otp_sent', true)
                ->withErrors(['mobile' => 'Enter a valid 10-digit mobile number.']);
        }

        $request->merge(['mobile' => $mobile]);

        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'otp' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ], [
            'otp.size' => 'OTP must be 4 digits.',
            'otp.regex' => 'OTP must be 4 digits.',
        ]);

        if (! $otp->verify($data['mobile'], WhatsAppOtpService::PURPOSE_STUDENT, $data['otp'])) {
            return back()
                ->withInput($request->only('mobile'))
                ->with('login_tab', 'otp')
                ->with('otp_sent', true)
                ->with('otp_mobile', $mobile)
                ->withErrors(['otp' => 'Invalid or expired OTP. Please request a new one.']);
        }

        $student = $auth->loginWithVerifiedOtp($data['mobile']);

        if (! $student) {
            return back()
                ->with('login_tab', 'otp')
                ->withErrors(['mobile' => 'This mobile number is not registered on the student portal.']);
        }

        return $this->completePortalLogin($request, $student->id);
    }

    public function changePassword(Request $request, StudentAuthService $auth): RedirectResponse
    {
        $student = $this->portalStudent();

        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'min:6', 'max:64', 'confirmed'],
        ]);

        if (! $auth->changePassword($student, $data['current_password'], $data['password'])) {
            return redirect()
                ->to(route('portal.dashboard').'#more')
                ->withErrors([
                    'current_password' => 'Current password is incorrect.',
                ]);
        }

        return redirect()
            ->to(route('portal.dashboard').'#more')
            ->with('portal_success', 'Your portal password has been updated.');
    }

    public function logout(): RedirectResponse
    {
        session()->forget('student_portal_id');

        return redirect()->route('home');
    }

    protected function completePortalLogin(Request $request, int $studentId): RedirectResponse
    {
        session(['student_portal_id' => $studentId]);

        $intended = session()->pull('portal_intended_url');

        if (filled($intended) && str_starts_with((string) $intended, url('/portal'))) {
            return redirect()->to((string) $intended);
        }

        return redirect()->route('portal.dashboard');
    }
}
