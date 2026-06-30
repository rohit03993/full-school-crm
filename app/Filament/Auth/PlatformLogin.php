<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class PlatformLogin extends Login
{
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        $response = parent::authenticate();

        $user = auth()->user();

        if ($user && ! $user->isPlatformOperator()) {
            auth()->logout();

            throw ValidationException::withMessages([
                'data.login' => 'This console is restricted to the software vendor.',
            ]);
        }

        return $response;
    }
}
