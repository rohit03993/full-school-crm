<?php

namespace App\Services;

use App\Models\User;
use App\Support\IndianMobileNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAccountService
{
    /**
     * @param  array{
     *     mobile?: string,
     *     current_password: string,
     *     new_password?: string|null,
     *     new_password_confirmation?: string|null
     * }  $data
     */
    public function updateOwnAccount(User $user, array $data): User
    {
        $currentPassword = (string) ($data['current_password'] ?? '');

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $mobile = IndianMobileNumber::normalize($data['mobile'] ?? $user->mobile);

        if ($mobile === null) {
            throw ValidationException::withMessages([
                'mobile' => 'Enter a valid 10-digit Indian mobile number.',
            ]);
        }

        $taken = User::query()
            ->where('mobile', $mobile)
            ->whereKeyNot($user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'mobile' => 'This mobile number is already used by another staff account.',
            ]);
        }

        $updates = ['mobile' => $mobile];

        $newPassword = trim((string) ($data['new_password'] ?? ''));

        if ($newPassword !== '') {
            if ($newPassword !== (string) ($data['new_password_confirmation'] ?? '')) {
                throw ValidationException::withMessages([
                    'new_password_confirmation' => 'New password confirmation does not match.',
                ]);
            }

            if (strlen($newPassword) < 8) {
                throw ValidationException::withMessages([
                    'new_password' => 'New password must be at least 8 characters.',
                ]);
            }

            $updates['password'] = $newPassword;
        }

        $user->update($updates);

        return $user->fresh();
    }
}
