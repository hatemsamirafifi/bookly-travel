<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Media\Actions\DeleteMediaAssetAction;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaFolder;
use App\Filament\Resources\MediaAssetResource\Pages;
use App\Models\User;
use Exception;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Media & Storage';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'media-assets';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function getPluralLabel(): string
    {
        return 'Media Library';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('file')
                ->label('Media file')
                ->disk((string) config('filesystems.media_disk', 'public'))
                ->directory('pending-uploads')
                ->preserveFilenames()
                ->maxSize(40960)
                ->helperText('JPG, PNG, WebP, AVIF or PDF, up to 40 MB.')
                ->live()
                ->visible(fn (string $operation): bool => $operation === 'create'),
            Section::make('Folder & tagging')
                ->schema([
                    Select::make('folder_id')
                        ->label('Folder')
                        ->options(MediaFolder::query()->orderBy('path')->pluck('path', 'id'))
                        ->placeholder('Root / uploads')
                        ->nullable(),
                    Select::make('tags')
                        ->label('Tags')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        ->nullable(),
                ])
                ->columns(2),
            Section::make('Alt text (localized)')
                ->schema([
                    Textarea::make('alt_text.en')->label('English alt text')->maxLength(512),
                    Textarea::make('alt_text.es')->label('Spanish alt text')->maxLength(512),
                    Textarea::make('alt_text.it')->label('Italian alt text')->maxLength(512),
                ])
                ->columns(3),
            Section::make('Title (localized)')
                ->schema([
                    Textarea::make('title.en')->label('English title')->maxLength(255),
                    Textarea::make('title.es')->label('Spanish title')->maxLength(255),
                    Textarea::make('title.it')->label('Italian title')->maxLength(255),
                ])
                ->columns(3),
            Section::make('Caption (localized)')
                ->schema([
                    Textarea::make('caption.en')->label('English caption')->maxLength(512),
                    Textarea::make('caption.es')->label('Spanish caption')->maxLength(512),
                    Textarea::make('caption.it')->label('Italian caption')->maxLength(512),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->label('')
                    ->getStateUsing(fn (MediaAsset $record): string => $record->previewUrl())
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->circular(false),
                Tables\Columns\TextColumn::make('original_filename')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('size_bytes')
                    ->label('Size')
                    ->formatStateUsing(fn ($state): string => static::humanSize((int) $state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('dimensions')
                    ->label('Dimensions')
                    ->getStateUsing(fn (MediaAsset $record): string => $record->width && $record->height
                        ? "{$record->width} x {$record->height}"
                        : '—'),
                Tables\Columns\TextColumn::make('folder.name')
                    ->label('Folder')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('usages_count')
                    ->label('Usages')
                    ->counts('usages')
                    ->sortable(),
                Tables\Columns\TextColumn::make('processing_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (MediaAsset $record): string => match (true) {
                        ! $record->fileExists() => 'danger',
                        $record->processing_status === MediaAsset::STATUS_READY => 'success',
                        $record->processing_status === MediaAsset::STATUS_FAILED => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (MediaAsset $record): string => ! $record->fileExists() ? 'Missing file' : ucfirst($record->processing_status)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('folder')
                    ->relationship('folder', 'name'),
                Tables\Filters\SelectFilter::make('processing_status')
                    ->label('Processing status')
                    ->options([
                        'pending' => 'Pending',
                        'ready' => 'Ready',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (MediaAsset $record): string => $record->original_filename)
                    ->authorize('delete')
                    ->action(function (MediaAsset $record): void {
                        try {
                            app(DeleteMediaAssetAction::class)->perform($record, static::currentUser());
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('Cannot delete media asset')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Human-readable byte size (KB/MB/GB).
     */
    public static function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        if ($bytes < 1073741824) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        return round($bytes / 1073741824, 1) . ' GB';
    }

    /**
     * Access: manage_media OR manage_cms OR manage_blog.
     */
    public static function canViewAny(): bool
    {
        $user = static::currentUser();

        if ($user === null) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_media')
            || $authz->can($user, 'manage_cms')
            || $authz->can($user, 'manage_blog');
    }

    public static function canGloballySearch(): bool
    {
        return static::canViewAny();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['original_filename', 'filename', 'storage_key'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->original_filename;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Processing status' => $record->processing_status,
            'Size' => static::humanSize((int) $record->size_bytes),
        ];
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaAssets::route('/'),
            'create' => Pages\CreateMediaAsset::route('/create'),
            'edit' => Pages\EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
