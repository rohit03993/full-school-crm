<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session()->has('student_portal_id')) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.login');
    }

    public function login(Request $request, StudentAuthService $auth): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'password' => ['required', 'digits:8'],
        ], [
            'mobile.regex' => 'Enter a valid 10-digit mobile number.',
            'password.digits' => 'Password must be your date of birth in DDMMYYYY format.',
        ]);

        $student = $auth->loginWithDob($data['mobile'], $data['password']);

        if (! $student) {
            return back()
                ->withInput($request->only('mobile'))
                ->withErrors(['mobile' => 'Invalid mobile number or date of birth.']);
        }

        session(['student_portal_id' => $student->id]);

        return redirect()->route('portal.dashboard');
    }

    public function logout(): RedirectResponse
    {
        session()->forget('student_portal_id');

        return redirect()->route('portal.login');
    }
}
