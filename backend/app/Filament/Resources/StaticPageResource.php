<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Translation\Models\TranslationFieldState;
use App\Filament\Components\HugeRteEditor;
use App\Filament\Components\MediaPicker;
use App\Filament\Resources\StaticPageResource\Pages;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * CMS / static-page management (Spec 013, US9, FR-015, ST-013-012/013).
 *
 * Lists, edits, and views localized static pages. The localized JSONB fields
 * (`title`, `body`, `meta_description`) are exposed as one field per supported
 * locale (`en`/`es`/`it`) on the edit form; `EditStaticPage` reassembles them
 * into the JSONB columns and routes persistence + audit through
 * `UpdateStaticPageAction`. Authorization is the `manage_cms` flag via
 * `StaticPagePolicy`.
 */
class StaticPageResource extends Resource
{
    protected static ?string $model = StaticPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Content & CMS';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'static-pages';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function canGloballySearch(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AdminAuthorizationService::class)->can($user, 'manage_cms');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['slug'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->slug;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Status' => $record->status,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(StaticPage::class, 'slug', ignoreRecord: true)
                    ->maxLength(120)
                    ->helperText('e.g. privacy, terms, about'),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ])
                    ->default('draft')
                    ->required(),
                Hidden::make('loaded_lock_version'),
                ...static::localizedSchema('title', fn (string $locale) => TextInput::make("title_{$locale}")
                    ->label(ucfirst($locale) . ' Title')
                    ->maxLength(255)
                    ->helperText(static::localizedStatusHint($locale, 'title'))),
                ...static::localizedSchema('body', fn (string $locale) => HugeRteEditor::make("body_{$locale}")
                    ->label(ucfirst($locale) . ' Body')
                    ->columnSpanFull()
                    ->helperText(static::localizedStatusHint($locale, 'body'))),
                ...static::localizedSchema('meta_description', fn (string $locale) => Textarea::make("meta_description_{$locale}")
                    ->label(ucfirst($locale) . ' Meta Description')
                    ->rows(2)
                    ->maxLength(255)
                    ->helperText(static::localizedStatusHint($locale, 'meta_description'))),
                ...static::localizedSchema('meta_title', fn (string $locale) => TextInput::make("meta_title_{$locale}")
                    ->label(ucfirst($locale) . ' Meta Title')
                    ->maxLength(255)
                    ->helperText(static::localizedStatusHint($locale, 'meta_title'))),
                TextInput::make('canonical_url')
                    ->label('Canonical URL')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                MediaPicker::make('og_image_media_id')
                    ->storesAttribute('id'),
                DateTimePicker::make('scheduled_at')
                    ->label('Scheduled Publication')
                    ->helperText('Optional future release date/time.'),
            ]);
    }

    /**
     * Badge-like per-field translation status hint for a locale's input;
     * nothing on create pages (no record) and nothing for EN fields.
     */
    private static function localizedStatusHint(string $locale, string $field): \Closure
    {
        return fn (?Model $record = null): ?string => match (true) {
            $record === null, $locale === 'en' => null,
            default => strtoupper($locale) . ': ' . static::fieldTranslationStatus($record, $field, $locale),
        };
    }

    private static function fieldTranslationStatus(Model $record, string $field, string $locale): string
    {
        $status = TranslationFieldState::query()
            ->where('translatable_type', $record->getMorphClass())
            ->where('translatable_id', $record->getKey())
            ->where('locale', $locale)
            ->where('field_name', $field)
            ->value('status');

        return $status ?? TranslationFieldState::STATUS_MISSING;
    }

    /**
     * Build one Section per locale for a localized field group.
     */
    public static function localizedSchema(string $field, \Closure $fieldFactory): array
    {
        return array_map(fn (string $locale) => Section::make(ucfirst($field) . " — {$locale}")
            ->schema([$fieldFactory($locale)])
            ->columns(2), StaticPage::LOCALES);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                    ]),
                Tables\Columns\TextColumn::make('updatedBy.name')
                    ->label('Updated By')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('slug', 'asc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Page')
                    ->schema([
                        Infolists\Components\TextEntry::make('slug')->label('Slug'),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge(),
                        Infolists\Components\TextEntry::make('updatedBy.name')->label('Updated By')->placeholder('—'),
                        Infolists\Components\TextEntry::make('published_at')->label('Published At')->dateTime()->placeholder('—'),
                    ])
                    ->columns(4),
                ...array_map(fn (string $locale) => Infolists\Components\Section::make("Content — {$locale}")
                    ->schema([
                        Infolists\Components\TextEntry::make("title.{$locale}")
                            ->label('Title')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make("body.{$locale}")
                            ->label('Body')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make("meta_description.{$locale}")
                            ->label('Meta Description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2), StaticPage::LOCALES),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaticPages::route('/'),
            'create' => Pages\CreateStaticPage::route('/create'),
            'edit' => Pages\EditStaticPage::route('/{record}/edit'),
            'view' => Pages\ViewStaticPage::route('/{record}'),
        ];
    }
}
