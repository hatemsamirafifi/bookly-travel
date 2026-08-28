<?php

namespace App\Filament\Resources;

use App\Domains\Blog\Actions\GeneratePreviewTokenAction;
use App\Domains\Blog\Models\BlogPost;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\Tour;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'blog-posts';

    protected static ?string $recordTitleAttribute = 'slug';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Information')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText('Unique URL identifier (auto-generated from English title if left blank).')
                            ->unique(BlogPost::class, 'slug', ignoreRecord: true)
                            ->maxLength(160),
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('blog_category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DateTimePicker::make('scheduled_at')
                            ->label('Scheduled Publication')
                            ->helperText('Optional future release date/time.'),
                        Toggle::make('is_featured')
                            ->label('Featured Story')
                            ->default(false),
                        FileUpload::make('cover_image_url')
                            ->label('Cover Image')
                            ->image()
                            ->directory('blog/covers')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Content — English (Primary)')
                    ->schema([
                        TextInput::make('title_en')
                            ->label('Title (EN)')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('excerpt_en')
                            ->label('Excerpt (EN)')
                            ->rows(3)
                            ->maxLength(500),
                        RichEditor::make('body_en')
                            ->label('Body (EN)')
                            ->required()
                            ->columnSpanFull(),
                        TextInput::make('meta_title_en')
                            ->label('Meta Title (EN)')
                            ->maxLength(255),
                        Textarea::make('meta_description_en')
                            ->label('Meta Description (EN)')
                            ->rows(2)
                            ->maxLength(255),
                    ]),

                Section::make('Content — Spanish (Español)')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('title_es')
                            ->label('Title (ES)')
                            ->maxLength(255),
                        Textarea::make('excerpt_es')
                            ->label('Excerpt (ES)')
                            ->rows(3)
                            ->maxLength(500),
                        RichEditor::make('body_es')
                            ->label('Body (ES)')
                            ->columnSpanFull(),
                        TextInput::make('meta_title_es')
                            ->label('Meta Title (ES)')
                            ->maxLength(255),
                        Textarea::make('meta_description_es')
                            ->label('Meta Description (ES)')
                            ->rows(2)
                            ->maxLength(255),
                    ]),

                Section::make('Content — Italian (Italiano)')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('title_it')
                            ->label('Title (IT)')
                            ->maxLength(255),
                        Textarea::make('excerpt_it')
                            ->label('Excerpt (IT)')
                            ->rows(3)
                            ->maxLength(500),
                        RichEditor::make('body_it')
                            ->label('Body (IT)')
                            ->columnSpanFull(),
                        TextInput::make('meta_title_it')
                            ->label('Meta Title (IT)')
                            ->maxLength(255),
                        Textarea::make('meta_description_it')
                            ->label('Meta Description (IT)')
                            ->rows(2)
                            ->maxLength(255),
                    ]),

                Section::make('Related Tours')
                    ->collapsible()
                    ->schema([
                        Repeater::make('related_tours')
                            ->schema([
                                Select::make('tour_id')
                                    ->label('Tour')
                                    ->options(fn () => Tour::where('status', 'published')
                                        ->with('translations')
                                        ->get()
                                        ->mapWithKeys(fn (Tour $tour) => [$tour->id => $tour->displayTitle('en')]))
                                    ->searchable()
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image_url')
                    ->label('Cover')
                    ->circular(),
                TextColumn::make('title')
                    ->label('Title')
                    ->state(fn (BlogPost $record) => $record->contentFor('title', 'en'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'archived' => 'danger',
                        default => 'warning',
                    }),
                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(function (BlogPost $record) {
                        $generator = app(GeneratePreviewTokenAction::class);
                        $tokenData = $generator->execute($record->slug);
                        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                        $previewUrl = "{$frontendUrl}/en/blog/{$record->slug}/preview?token={$tokenData['token']}";

                        Notification::make()
                            ->title('Preview Link Generated')
                            ->body("Preview token active for 30 minutes: {$previewUrl}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
            'view' => Pages\ViewBlogPost::route('/{record}'),
        ];
    }
}
