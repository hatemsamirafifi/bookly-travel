<?php

namespace App\Domains\Admin\Services;

use App\Domains\Admin\Models\AdminPermission;
use App\Domains\Admin\Models\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Admin user lifecycle service (Spec 035, T-A03).
 *
 * All create/update/suspend/reactivate paths funnel through here so two
 * invariants always hold: a non-super-admin can never grant a permission they
 * do not hold, and the last active Super Admin can never be demoted or
 * suspended. Every mutation is written to the governance audit trail and
 * suspension revokes Sanctum tokens and database sessions.
 */
class AdminUserService
{
    public function __construct(
        private readonly AdminAuthorizationService $authz,
        private readonly GovernanceAuditService $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, status?: string, locale?: string, flags?: array<string, bool>, roles?: array<int, int|string>}  $data
     */
    public function createAdmin(User $actor, array $data): User
    {
        $this->assertNoPrivilegeEscalation($actor, $data);

        return DB::transaction(function () use ($actor, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'admin',
                'status' => $data['status'] ?? 'active',
                'locale' => $data['locale'] ?? 'en',
            ]);

            $this->syncFlags($user, $data['flags'] ?? []);
            $this->syncRoles($user, $data['roles'] ?? [], $actor);

            $user = $user->fresh(['adminPermission', 'adminRoles']);

            $this->audit->log($actor, 'admin_user.create', $user, null, $this->snapshot($user), [
                'flags' => $this->grantedFlags($user),
                'roles' => $this->roleSlugs($user),
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, status?: string, locale?: string, flags?: array<string, bool>, roles?: array<int, int|string>}  $data
     */
    public function updateAdmin(User $actor, User $target, array $data): User
    {
        $this->assertNoPrivilegeEscalation($actor, $data);

        return DB::transaction(function () use ($actor, $target, $data): User {
            $before = $this->snapshot($target);

            $profile = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'status' => $data['status'] ?? null,
                'locale' => $data['locale'] ?? null,
            ], fn ($value): bool => $value !== null && $value !== '');

            if ($profile !== []) {
                $target->fill($profile);
            }

            if (! empty($data['password'])) {
                $target->password = $data['password'];
            }

            $newStatus = $profile['status'] ?? ($target->status ?? 'active');
            $newRoles = array_key_exists('roles', $data) ? $data['roles'] : null;

            if ($this->authz->isSuperAdmin($target)) {
                $demotesSuperAdmin = $newRoles !== null && ! $this->roleIdsIncludeSuperAdmin($newRoles);

                if ($newStatus !== 'active' || $demotesSuperAdmin) {
                    $this->assertNotLastActiveSuperAdmin($target);
                }
            }

            $target->save();

            if (array_key_exists('flags', $data)) {
                $this->syncFlags($target, $data['flags']);
            }

            if ($newRoles !== null) {
                $this->syncRoles($target, $newRoles, $actor);
            }

            $target = $target->fresh(['adminPermission', 'adminRoles']);

            $this->audit->log($actor, 'admin_user.update', $target, $before, $this->snapshot($target), [
                'flags' => $this->grantedFlags($target),
                'roles' => $this->roleSlugs($target),
            ]);

            if (array_key_exists('flags', $data) || $newRoles !== null) {
                $this->audit->log($actor, 'admin_user.permissions_updated', $target, $before, $this->snapshot($target), [
                    'flags' => $this->grantedFlags($target),
                    'roles' => $this->roleSlugs($target),
                ]);
            }

            return $target;
        });
    }

    public function suspendAdmin(User $actor, User $target, ?string $reason = null): User
    {
        $this->assertNotLastActiveSuperAdmin($target);

        $before = $this->snapshot($target);

        $target->forceFill([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();

        $this->revokeAccess($target);

        $target = $target->fresh(['adminPermission', 'adminRoles']);

        $this->audit->log($actor, 'admin_user.suspend', $target, $before, $this->snapshot($target), [
            'reason' => $reason,
        ]);

        return $target;
    }

    public function reactivateAdmin(User $actor, User $target): User
    {
        $before = $this->snapshot($target);

        $target->forceFill([
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        $target = $target->fresh(['adminPermission', 'adminRoles']);

        $this->audit->log($actor, 'admin_user.reactivate', $target, $before, $this->snapshot($target));

        return $target;
    }

    /**
     * A non-super-admin may only grant flags (directly or through a role) that
     * they themselves hold.
     *
     * @param  array{flags?: array<string, bool>, roles?: array<int, int|string>}  $data
     */
    public function assertNoPrivilegeEscalation(User $actor, array $data): void
    {
        if ($this->authz->isSuperAdmin($actor)) {
            return;
        }

        $requested = array_keys(array_filter($data['flags'] ?? []));

        $roleIds = $data['roles'] ?? [];

        if ($roleIds !== []) {
            foreach (AdminRole::query()->whereIn('id', $roleIds)->get() as $role) {
                if ($role->slug === 'super_admin') {
                    $requested = array_merge($requested, AdminAuthorizationService::FLAGS);

                    continue;
                }

                foreach ($role->permissions ?? [] as $flag => $granted) {
                    if ($granted) {
                        $requested[] = $flag;
                    }
                }
            }
        }

        foreach (array_unique($requested) as $flag) {
            if (! $this->authz->can($actor, $flag)) {
                throw ValidationException::withMessages([
                    'flags' => "You cannot grant the [{$flag}] permission because you do not hold it.",
                ]);
            }
        }
    }

    /**
     * Guard the platform against losing its final active Super Admin.
     */
    public function assertNotLastActiveSuperAdmin(User $target): void
    {
        if (($target->status ?? 'active') !== 'active' || ! $this->authz->isSuperAdmin($target)) {
            return;
        }

        $hasAnother = User::query()
            ->where('role', 'admin')
            ->where('id', '!=', $target->getKey())
            ->where(function ($query): void {
                $query->where('status', 'active')->orWhereNull('status');
            })
            ->with(['adminPermission', 'adminRoles'])
            ->get()
            ->contains(fn (User $user): bool => $this->authz->isSuperAdmin($user));

        if (! $hasAnother) {
            throw ValidationException::withMessages([
                'status' => ['Cannot suspend, demote, or delete the last active Super Admin.'],
            ]);
        }
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function syncFlags(User $user, array $flags): void
    {
        $normalised = [];

        foreach (AdminAuthorizationService::FLAGS as $flag) {
            $normalised[$flag] = (bool) ($flags[$flag] ?? false);
        }

        /** @var AdminPermission $permission */
        $permission = $user->adminPermission()->firstOrNew([]);
        $permission->flags = $normalised;
        $permission->save();
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    private function syncRoles(User $user, array $roleIds, ?User $assignedBy = null): void
    {
        $validIds = AdminRole::query()->whereIn('id', $roleIds)->pluck('id')->all();

        $pivotData = array_fill_keys(
            array_map(fn ($id): int => (int) $id, $validIds),
            ['assigned_by' => $assignedBy?->getKey()],
        );

        $user->adminRoles()->sync($pivotData);
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    private function roleIdsIncludeSuperAdmin(array $roleIds): bool
    {
        return AdminRole::query()
            ->whereIn('id', $roleIds)
            ->where('slug', 'super_admin')
            ->exists();
    }

    private function revokeAccess(User $user): void
    {
        $user->tokens()->delete();

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        $user->loadMissing(['adminPermission', 'adminRoles']);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status ?? 'active',
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'flags' => $user->adminPermission->flags ?? [],
            'roles' => $this->roleSlugs($user),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function grantedFlags(User $user): array
    {
        return array_keys(array_filter($user->adminPermission->flags ?? []));
    }

    /**
     * @return array<int, string>
     */
    private function roleSlugs(User $user): array
    {
        return $user->adminRoles->pluck('slug')->values()->all();
    }
}
