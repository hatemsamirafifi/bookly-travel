<?php

namespace App\Filament\Resources;

use App\Domains\Blog\Models\AuthorProfile;
use App\Filament\Resources\AuthorProfileResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Author profile management (Admin 2.0 Phase G, T-G01).
 *
 * JSONB-localized `display_name`/`bio` columns plus an avatar URL, mapped
 * 1:1 to admin/blog users (`author_profiles.user_id` is unique).
 */
class AuthorProfileResource extends Resource
{
    /**
     * @var list<string>
     */
    public const LOCALES = ['en', 'es', 'it'];

    protected static ?string $model = AuthorProfile::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationGroup = 'Content & CMS';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'author-profiles';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('user_id')
                    ->label('User')
                    ->options(self::userOptions())
                    ->required()
                    ->unique(table: AuthorProfile::class, column: 'user_id', ignoreRecord: true)
                    ->searchable(),
                TextInput::make('avatar_url')
                    ->label('Avatar URL')
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                ...AuthorProfileResource::localeSchema('display_name', 'Display Name', TextInput::class),
                ...AuthorProfileResource::localeSchema('bio', 'Bio', Textarea::class),
            ]);
    }

    /**
     * Build one Section per locale with the appropriate input type.
     *
     * @param  class-string<TextInput|Textarea>  $component
     */
    private static function localeSchema(string $field, string $label, string $component): array
    {
        return array_map(
            function (string $locale) use ($field, $label, $component) {
                $input = $component::make("{$field}_{$locale}")
                    ->label(ucfirst($locale) . ' ' . $label)
                    ->maxLength($field === 'display_name' ? 255 : 1000);

                if ($component === Textarea::class) {
                    $input->rows(4);
                }

                return Section::make("{$label} — {$locale}")
                    ->schema([$input->columnSpanFull()]);
            },
            self::LOCALES
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Display Name')
                    ->state(fn (AuthorProfile $record) => $record->contentFor('display_name', 'en'))
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('avatar_url')
                    ->label('Avatar')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Reassemble flat `field_{locale}` inputs into JSONB columns.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, string>>  $existing
     * @return array<string, mixed>
     */
    public static function mergeLocalizedFields(array $data, array $existing = []): array
    {
        foreach (['display_name', 'bio'] as $field) {
            $values = $existing[$field] ?? [];

            foreach (self::LOCALES as $locale) {
                $value = $data["{$field}_{$locale}"] ?? null;
                unset($data["{$field}_{$locale}"]);

                if (is_string($value) && trim($value) !== '') {
                    $values[$locale] = $value;
                }
            }

            $data[$field] = $values;
        }

        return $data;
    }

    public static function userOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthorProfiles::route('/'),
            'create' => Pages\CreateAuthorProfile::route('/create'),
            'edit' => Pages\EditAuthorProfile::route('/{record}/edit'),
        ];
    }
}
