<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Events\AccountLockedOut;
use App\Domains\Auth\Events\LoginFailed;
use App\Domains\Auth\Events\TravelerLoggedIn;
use App\Models\AuthAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthenticateTravelerAction
{
    /**
     * Authenticate a traveler.
     */
    public function execute(array $data): array
    {
        $normalizedEmail = strtolower(trim($data['email']));

        $user = User::where('email', $normalizedEmail)->first();

        if (! $user) {
            event(new LoginFailed($normalizedEmail, null));

            return [
                'success' => false,
                'message' => 'Invalid email or password.',
            ];
        }

        return DB::transaction(function () use ($user, $data, $normalizedEmail) {
            // Re-read with row lock to prevent concurrent mutation races
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password.',
                ];
            }

            if ($user->locked_until && $user->locked_until->isFuture()) {
                DB::afterCommit(function () use ($normalizedEmail, $user) {
                    event(new LoginFailed($normalizedEmail, $user, rejectedDueToLockout: true));
                });

                return [
                    'success' => false,
                    'locked' => true,
                    'message' => 'Too many failed attempts. Please try again later.',
                ];
            }

            if (! Hash::check($data['password'], $user->password)) {
                $user->failed_login_count = ($user->failed_login_count ?? 0) + 1;

                if ($user->failed_login_count > 0 && $user->failed_login_count % 5 == 0) {
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
                }

                $user->save();

                DB::afterCommit(function () use ($normalizedEmail, $user) {
                    event(new LoginFailed($normalizedEmail, $user));
                    if ($user->locked_until && $user->locked_until->isFuture()) {
                        event(new AccountLockedOut($user));
                    }
                });

                return [
                    'success' => false,
                    'message' => 'Invalid email or password.',
                ];
            }

            $user->failed_login_count = 0;
            $user->locked_until = null;
            $user->last_login_at = now();
            $user->save();

            $token = $user->createToken('auth-token');

            DB::afterCommit(function () use ($user) {
                event(new TravelerLoggedIn($user));
            });

            return [
                'success' => true,
                'user' => $user,
                'token' => $token->plainTextToken,
            ];
        });
    }
}
