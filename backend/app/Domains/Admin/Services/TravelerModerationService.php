<?php

namespace App\Domains\Admin\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Traveler account moderation (Admin 2.0, T-B02).
 *
 * Suspends/reactivates traveler accounts (`users.role = traveler`).
 * Suspension nullifies the account, hard-deletes every Sanctum access
 * token (active API sessions die instantly), and writes an immutable
 * `traveler.suspend` governance audit entry with the reason. Reactivation
 * restores the account status and clears suspension fields with a
 * `traveler.reactivate` audit entry.
 */
class TravelerModerationService
{
    public function __construct(private readonly GovernanceAuditService $audit) {}

    /**
     * Suspend a traveler account.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException 403 for a
     *                                                           non-admin actor, 422 when the target is not a traveler
     *                                                           or `reason` is empty.
     */
    public function suspend(User $actor, User $traveler, string $reason): User
    {
        $this->authorize($actor);
        $this->assertTraveler($traveler);

        $reasonText = trim($reason);
        abort_if($reasonText === '', 422, 'A suspension reason is required.');
        abort_unless(
            $traveler->status !== 'suspended',
            422,
            'Traveler is already suspended.',
        );

        return DB::transaction(function () use ($actor, $traveler, $reasonText): User {
            $before = [
                'status' => $traveler->status,
                'suspended_at' => $traveler->suspended_at,
                'suspension_reason' => $traveler->suspension_reason,
            ];

            $traveler->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $reasonText,
            ]);

            $traveler->tokens()->delete();

            $this->audit->log($actor, 'traveler.suspend', $traveler, $before, [
                'status' => 'suspended',
                'suspended_at' => $traveler->suspended_at?->toIso8601String(),
                'suspension_reason' => $reasonText,
            ], [
                'reason' => $reasonText,
            ]);

            return $traveler;
        });
    }

    /**
     * Reactivate a suspended traveler account.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException 403 for a
     *                                                           non-admin actor, 422 when the target is not a traveler.
     */
    public function reactivate(User $actor, User $traveler): User
    {
        $this->authorize($actor);
        $this->assertTraveler($traveler);
        abort_unless(
            $traveler->status === 'suspended',
            422,
            'Traveler is not suspended.',
        );

        return DB::transaction(function () use ($actor, $traveler): User {
            $before = [
                'status' => $traveler->status,
                'suspended_at' => $traveler->suspended_at,
                'suspension_reason' => $traveler->suspension_reason,
            ];

            $traveler->update([
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            $this->audit->log($actor, 'traveler.reactivate', $traveler, $before, [
                'status' => 'active',
                'suspended_at' => null,
                'suspension_reason' => null,
            ]);

            return $traveler;
        });
    }

    /**
     * Admins may moderate travelers with either the traveler-management
     * flag or the general user-management flag (T-B02).
     */
    public static function isAuthorized(User $user): bool
    {
        if ($user->role !== 'admin' || ($user->status ?? 'active') !== 'active') {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_travelers')
            || $authz->can($user, 'manage_users');
    }

    private function authorize(User $actor): void
    {
        abort_unless(
            static::isAuthorized($actor),
            403,
            'You are not authorized to moderate travelers.',
        );
    }

    private function assertTraveler(User $traveler): void
    {
        abort_unless($traveler->role === 'traveler', 422, 'Only traveler accounts can be moderated.');
    }
}
