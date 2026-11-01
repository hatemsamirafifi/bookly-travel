<?php

namespace App\Filament\Resources\AdminRoleResource\Pages;

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Filament\Resources\AdminRoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAdminRole extends EditRecord
{
    protected static string $resource = AdminRoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = array_keys(array_filter(
            $this->getRecord()->permissions ?? [],
        ));

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof AdminRole);

        $before = $record->withoutRelations()->toArray();

        $changes = array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== '');

        $record->fill($changes);

        if (! $record->is_system) {
            $record->slug = $data['slug'];
            $record->permissions = array_fill_keys(
                array_map(fn ($flag): string => (string) $flag, (array) ($data['permissions'] ?? [])),
                true,
            );
        }

        $record->save();

        $fresh = $record->fresh()?->withoutRelations();

        app(GovernanceAuditService::class)->log(
            auth()->user(),
            'admin_role.update',
            $record,
            $before,
            $fresh?->toArray(),
        );

        return $fresh ?? $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()->visible(fn (AdminRole $record): bool => ! $record->is_system),
        ];
    }
}
