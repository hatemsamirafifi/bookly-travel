<?php

namespace App\Filament\Resources;

use App\Domains\Blog\Models\BlogTag;
use App\Filament\Resources\BlogTagResource\Pages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Blog tag management (Admin 2.0 Phase G, T-G01).
 *
 * JSONB-localized tag names (`name.en`/`name.es`/`name.it`) keyed by a unique
 * URL slug, linked to posts through the `blog_post_tags` pivot.
 */
class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Content & CMS';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'blog-tags';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(BlogTag::class, 'slug', ignoreRecord: true)
                    ->maxLength(120),
                ...BlogTagResource::tagNameSchema(),
            ]);
    }

    /**
     * Build one Section per locale for the localized tag name.
     */
    public static function tagNameSchema(): array
    {
        return array_map(fn (string $locale) => Section::make("Name — {$locale}")
            ->schema([TextInput::make("name_{$locale}")
                ->label(ucfirst($locale) . ' Name')
                ->maxLength(120)]), BlogTag::LOCALES);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (BlogTag $record) => $record->contentFor('en'))
                    ->placeholder('—'),
                TextColumn::make('posts_count')
                    ->counts('posts')
                    ->label('Posts'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('slug', 'asc');
    }

    /**
     * Reassemble flat `name_{locale}` inputs into the JSONB `name` column.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $existing
     * @return array<string, mixed>
     */
    public static function mergeLocalizedNames(array $data, array $existing = []): array
    {
        $name = $existing;

        foreach (BlogTag::LOCALES as $locale) {
            $value = $data["name_{$locale}"] ?? null;
            unset($data["name_{$locale}"]);

            if (is_string($value) && trim($value) !== '') {
                $name[$locale] = $value;
            }
        }

        $data['name'] = $name;

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogTags::route('/'),
            'create' => Pages\CreateBlogTag::route('/create'),
            'edit' => Pages\EditBlogTag::route('/{record}/edit'),
        ];
    }
}
