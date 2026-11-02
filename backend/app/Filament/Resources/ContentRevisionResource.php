<?php

namespace App\Filament\Resources;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Services\ContentRevisionService;
use App\Filament\Resources\ContentRevisionResource\Pages;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

/**
 * Immutable content revision history (Admin 2.0 Phase G, T-G03).
 *
 * Read-only inspection of every stored content snapshot, with a `restore`
 * action that reverts the revisionable to a prior snapshot and appends a new
 * version (history itself is append-only, never rewritten). Access requires
 * any of `manage_revisions` / `manage_cms` / `manage_blog` via
 * `ContentRevisionPolicy`.
 */
class ContentRevisionResource extends Resource
{
    protected static ?string $model = ContentRevision::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Content & CMS';

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'content-revisions';

    protected static ?string $recordTitleAttribute = 'change_summary';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Placeholder::make('revisionable'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('revisionable_type')
                    ->label('Content Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('revisionable_id')
                    ->label('Content ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->label('Version')
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_summary')
                    ->label('Change Summary')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Saved By')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Saved At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->requiresConfirmation()
                    ->action(function (ContentRevision $record): void {
                        app(ContentRevisionService::class)->restore($record, auth()->user());
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('viewAny', ContentRevision::class);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentRevisions::route('/'),
            'view' => Pages\ViewContentRevision::route('/{record}'),
        ];
    }
}
