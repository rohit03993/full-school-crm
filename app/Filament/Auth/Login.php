<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
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
            ->helperText('Mobile with or without +91. Staff sign in with mobile and password.');
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        $login = trim((string) ($data['login'] ?? ''));
        $mobile = \App\Support\IndianMobileNumber::normalize($login);

        if ($mobile === null) {
            throw ValidationException::withMessages([
                'data.login' => 'Enter a valid 10-digit mobile number (with or without +91).',
            ]);
        }

        return [
            'mobile' => $mobile,
            'password' => $data['password'],
        ];
    }
}
