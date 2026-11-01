<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Filament\Resources\AdminRoleResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminRoleResource extends Resource
{
    protected static ?string $model = AdminRole::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Administration & Governance';

    protected static ?string $navigationLabel = 'Roles & Permissions';

    protected static ?string $slug = 'admin-roles';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return static::canManageRoles();
    }

    public static function canCreate(): bool
    {
        return static::canManageRoles();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageRoles();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageRoles() && ! $record->is_system;
    }

    private static function canManageRoles(): bool
    {
        $user = auth()->user();
        $auth = app(AdminAuthorizationService::class);

        return $user instanceof User
            && ($auth->can($user, 'manage_roles') || $auth->can($user, 'manage_users'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('users');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(AdminRole::class, 'slug', ignoreRecord: true)
                            ->maxLength(64)
                            ->disabled(fn (?AdminRole $record): bool => (bool) $record?->is_system)
                            ->dehydrated(),
                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('permissions')
                            ->label('Permission flags')
                            ->options(collect(AdminAuthorizationService::FLAGS)
                                ->mapWithKeys(fn (string $flag): array => [
                                    $flag => AdminUserResource::flagLabel($flag),
                                ])
                                ->all())
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->placeholder('—')
                    ->toggleable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('is_system')
                    ->label('System')
                    ->badge()
                    ->colors([
                        'success' => fn ($state): bool => (bool) $state,
                        'gray' => fn ($state): bool => ! (bool) $state,
                    ])
                    ->formatStateUsing(fn ($state): string => $state ? 'System' : 'Custom'),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->alignCenter()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('seed_system_roles')
                    ->label('Seed system roles')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->modalHeading('Seed system roles')
                    ->modalDescription('Creates or refreshes the 7 canonical system role templates.')
                    ->action(fn (): string => AdminRole::ensureSystemRoles()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (AdminRole $record): bool => ! $record->is_system),
            ])
            ->bulkActions([])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminRoles::route('/'),
            'create' => Pages\CreateAdminRole::route('/create'),
            'edit' => Pages\EditAdminRole::route('/{record}/edit'),
        ];
    }
}
