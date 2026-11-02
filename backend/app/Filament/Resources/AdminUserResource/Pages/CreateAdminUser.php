<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Domains\Admin\Services\AdminUserService;
use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function handleRecordCreation(array $data): User
    {
        return app(AdminUserService::class)->createAdmin(auth()->user(), [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => $data['status'],
            'flags' => $this->flagsToMap($data['flags'] ?? []),
            'roles' => $this->rolesToIds($data['roles'] ?? []),
        ]);
    }

    /**
     * @param  array<int, string>  $selected
     * @return array<string, bool>
     */
    private function flagsToMap(array $selected): array
    {
        return array_fill_keys(array_map(
            fn (string $flag): string => (string) $flag,
            $selected,
        ), true);
    }

    /**
     * @param  array<int, int|string>  $roles
     * @return array<int, int>
     */
    private function rolesToIds(array $roles): array
    {
        return array_map(fn ($id): int => (int) $id, $roles);
    }
}
