<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Domains\Admin\Services\AdminUserService;
use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['flags'] = array_keys(array_filter(
            $this->getRecord()->adminPermission?->flags ?? [],
        ));

        $data['roles'] = $this->getRecord()->adminRoles->pluck('id')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof User);

        if (blank($data['password'])) {
            unset($data['password']);
        }

        return app(AdminUserService::class)->updateAdmin(auth()->user(), $record, [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'status' => $data['status'],
            'flags' => array_fill_keys((array) ($data['flags'] ?? []), true),
            'roles' => array_map(fn ($id): int => (int) $id, (array) ($data['roles'] ?? [])),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
