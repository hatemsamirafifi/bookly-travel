<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordAction
{
    /**
     * Reset a traveler's password.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function execute(array $data): array
    {
        $normalizedEmail = strtolower(trim($data['email']));

        $user = User::where('email', $normalizedEmail)->first();

        if (! $user) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ];
        }

        $status = Password::reset(
            [
                'email' => $normalizedEmail,
                'token' => $data['token'],
                'password' => $data['password'],
            ],
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();

                // Invalidate all existing reset tokens for this email
                DB::table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return [
                'success' => true,
                'message' => 'Password reset successfully.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid or expired reset token.',
        ];
    }
}
