<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Models\AdminRole;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\AdminUserService;
use App\Filament\Resources\AdminUserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration & Governance';

    protected static ?string $navigationLabel = 'Admin Users';

    protected static ?string $pluralLabel = 'Admin users';

    protected static ?string $slug = 'admin-users';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return static::canManageUsers();
    }

    public static function canGloballySearch(): bool
    {
        return static::canViewAny();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Email' => $record->email,
            'Role' => $record->role,
            'Status' => $record->status,
        ];
    }

    public static function canCreate(): bool
    {
        return static::canManageUsers();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageUsers();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageUsers();
    }

    private static function canManageUsers(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminAuthorizationService::class)->can($user, 'manage_users');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'admin')
            ->with(['adminPermission', 'adminRoles']);
    }

    /**
     * @return array<string, string>
     */
    public static function flagOptions(): array
    {
        return collect(AdminAuthorizationService::FLAGS)
            ->mapWithKeys(fn (string $flag): array => [$flag => static::flagLabel($flag)])
            ->all();
    }

    public static function flagLabel(string $flag): string
    {
        return str($flag)
            ->replace('_', ' ')
            ->title()
            ->replace('Cms', 'CMS')
            ->toString();
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Account')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Roles')
                    ->schema([
                        Forms\Components\CheckboxList::make('roles')
                            ->label('Assign roles')
                            ->options(fn (): array => AdminRole::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->helperText('Role permissions are unioned with direct permissions below.')
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Direct permissions')
                    ->schema([
                        Forms\Components\CheckboxList::make('flags')
                            ->label('Direct permission flags')
                            ->options(static::flagOptions())
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
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'suspended',
                    ]),
                Tables\Columns\TextColumn::make('adminRoles.name')
                    ->label('Roles')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('suspended_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->status !== 'suspended')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend admin')
                    ->modalDescription('Suspension revokes all API tokens and web sessions immediately.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ])
                    ->action(function (User $record, array $data): void {
                        app(AdminUserService::class)->suspendAdmin(auth()->user(), $record, $data['reason']);
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate admin')
                    ->action(function (User $record): void {
                        app(AdminUserService::class)->reactivateAdmin(auth()->user(), $record);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }
}
