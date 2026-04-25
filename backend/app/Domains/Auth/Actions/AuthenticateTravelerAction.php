<?php

namespace App\Domains\Auth\Actions;

use App\Models\User;
use App\Models\AuthAuditLog;
use Illuminate\Support\Facades\Hash;
use App\Domains\Auth\Events\TravelerLoggedIn;
use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Events\AccountLockedOut;

class AuthenticateTravelerAction
{
    /**
     * Authenticate a traveler.
     *
     * @param array $data
     * @return array
     */
    public function execute(array $data): array
    {
        $normalizedEmail = strtolower(trim($data['email']));
        
        $user = User::where('email', $normalizedEmail)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            return [
                'success' => false,
                'locked' => true,
                'message' => 'Too many failed attempts. Please try again later.'
            ];
        }

        if (!$user || !Hash::check($data['password'], $user->password)) {
            if ($user) {
                $user->failed_login_count = ($user->failed_login_count ?? 0) + 1;
                $user->save();
            }

            event(new LoginFailed($normalizedEmail, $user));

            if ($user && $user->failed_login_count >= 5) {
                $lastLogin = AuthAuditLog::where('user_id', $user->id)
                    ->where('event_type', 'login_success')
                    ->latest()
                    ->first();

                $lockoutsQuery = AuthAuditLog::where('user_id', $user->id)
                    ->where('event_type', 'account_lockout');

                if ($lastLogin) {
                    $lockoutsQuery->where('created_at', '>', $lastLogin->created_at);
                }

                $lockoutCount = $lockoutsQuery->count();

                $tierDuration = 1; // 1 minute default
                if ($lockoutCount == 1) {
                    $tierDuration = 5; // 2nd lockout
                } elseif ($lockoutCount >= 2) {
                    $tierDuration = 30; // 3rd+ lockout
                }

                $user->locked_until = now()->addMinutes($tierDuration);
                $user->save();

                event(new AccountLockedOut($user));
            }

            return [
                'success' => false,
                'message' => 'Invalid email or password.'
            ];
        }

        $user->failed_login_count = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth-token');

        event(new TravelerLoggedIn($user));

        return [
            'success' => true,
            'user' => $user,
            'token' => $token->plainTextToken
        ];
    }
}
