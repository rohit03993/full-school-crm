<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Throwable;

class CrmLivewireErrors
{
    public static function register(): void
    {
        Livewire::listen('failed', function (mixed $component, Throwable $exception): void {
            Log::error('Livewire component failed', [
                'component' => is_object($component) ? $component::class : null,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            if (! Auth::check()) {
                return;
            }

            $message = self::messageFor($exception);

            Notification::make()
                ->title('Action failed')
                ->body($message)
                ->danger()
                ->persistent()
                ->send();
        });
    }

    public static function messageFor(Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        if ($message === '') {
            return 'An unexpected error occurred. Check storage/logs/laravel.log for details.';
        }

        if (app()->hasDebugModeEnabled()) {
            return Str::limit($message.' ('.basename($exception->getFile()).':'.$exception->getLine().')', 500);
        }

        return Str::limit($message, 500);
    }
}
