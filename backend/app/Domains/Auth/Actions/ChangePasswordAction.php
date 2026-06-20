<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\PasswordChanged;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChangePasswordAction
{
    /**
     * Change a traveler's password.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function execute(User $user, string $currentPassword, string $newPassword): array
    {
        if (! Hash::check($currentPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect.',
            ];
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        // Invalidate all existing reset tokens for this email
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        event(new PasswordChanged($user));

        return [
            'success' => true,
            'message' => 'Password changed successfully.',
        ];
    }
}
