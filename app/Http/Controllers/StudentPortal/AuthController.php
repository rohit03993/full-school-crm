<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Services\StudentAuthService;
use App\Support\IndianMobileNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    use ResolvesPortalStudent;

    public function showLogin(StudentAuthService $auth): View|RedirectResponse
    {
        if (session()->has('student_portal_id')) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.login', [
            'loginHint' => $auth->portalLoginHint(),
        ]);
    }

    public function login(Request $request, StudentAuthService $auth): RedirectResponse
    {
        $mobile = IndianMobileNumber::normalize($request->input('mobile'));

        if ($mobile === null) {
            return back()
                ->withInput($request->only('mobile'))
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
                ->withErrors(['mobile' => 'Invalid mobile number or password.']);
        }

        session(['student_portal_id' => $student->id]);

        $intended = session()->pull('portal_intended_url');

        if (filled($intended) && str_starts_with((string) $intended, url('/portal'))) {
            return redirect()->to((string) $intended);
        }

        return redirect()->route('portal.dashboard');
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
}
