<?php

namespace App\Filament\Resources\AdminRoleResource\Pages;

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Filament\Resources\AdminRoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateAdminRole extends CreateRecord
{
    protected static string $resource = AdminRoleResource::class;

    protected function handleRecordCreation(array $data): AdminRole
    {
        $role = AdminRole::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'description' => $data['description'] ?? null,
            'permissions' => array_fill_keys(
                array_map(fn ($flag): string => (string) $flag, (array) ($data['permissions'] ?? [])),
                true,
            ),
            'is_system' => false,
        ]);

        app(GovernanceAuditService::class)->log(
            auth()->user(),
            'admin_role.create',
            $role,
            null,
            $role->withoutRelations()->toArray(),
        );

        return $role;
    }
}
