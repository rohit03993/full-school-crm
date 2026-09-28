<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

class PlatformLogin extends Login
{
    public function mount(): void
    {
        BaseLogin::mount();
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Mobile number')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->tel()
            ->maxLength(14)
            ->placeholder('10-digit mobile or +91…')
            ->helperText('Vendor sign in with mobile and password.');
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        $response = BaseLogin::authenticate();

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
