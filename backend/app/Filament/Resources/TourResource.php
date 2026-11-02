<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\ArchiveTourAction;
use App\Domains\Admin\Actions\RejectTourAction;
use App\Domains\Admin\Actions\UnpublishTourAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Services\TourTranslationService;
use App\Filament\Components\HugeRteEditor;
use App\Filament\Resources\TourResource\Pages;
use App\Models\Tour;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Marketplace Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending_review')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canGloballySearch(): bool
    {
        $user = auth()->user();

        return $user !== null && app(AdminAuthorizationService::class)->can($user, 'manage_tours');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['slug', 'location'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->slug;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Location' => $record->location,
            'Status' => $record->status,
        ];
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General Information')
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(Tour::class, 'slug', ignoreRecord: true)
                            ->maxLength(160),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('partner_id')
                            ->relationship('partner', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => optional($record->profile)->company_name ?? "Partner #{$record->id}")
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_review' => 'Pending Review',
                                'published' => 'Published',
                                'rejected' => 'Rejected',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->required()
                            ->maxLength(150),
                        Forms\Components\TextInput::make('location_slug')
                            ->maxLength(150),
                        Forms\Components\TextInput::make('price_amount')
                            ->label('Price in Cents (e.g. 8500 = €85.00)')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('duration_minutes')
                            ->numeric()
                            ->default(120),
                        Forms\Components\TextInput::make('duration_label')
                            ->default('2 hours'),
                        Forms\Components\TextInput::make('group_size_min')
                            ->numeric()
                            ->default(1),
                        Forms\Components\TextInput::make('group_size_max')
                            ->numeric()
                            ->default(15),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),
                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Cover Image')
                            ->image()
                            ->disk('public')
                            ->directory('tours')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Translations')
                    ->schema([
                        Forms\Components\Repeater::make('translations')
                            ->relationship('translations')
                            ->schema([
                                Forms\Components\Select::make('locale')
                                    ->options([
                                        'en' => 'English',
                                        'es' => 'Spanish',
                                        'it' => 'Italian',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255),
                                HugeRteEditor::make('description')
                                    ->columnSpanFull(),
                                Forms\Components\Textarea::make('meeting_point')
                                    ->rows(2),
                                Forms\Components\Textarea::make('cancellation_policy')
                                    ->rows(2),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->defaultItems(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('cover_image_url')
                    ->label('Cover')
                    ->disk('public')
                    ->circular(),
                Tables\Columns\TextColumn::make('translations.title')
                    ->label('Title')
                    ->searchable()
                    ->limit(40)
                    ->getStateUsing(function (Tour $record) {
                        $en = $record->translations->firstWhere('locale', 'en');

                        return $en?->title ?? $record->slug;
                    }),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('Partner')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending_review',
                        'success' => 'published',
                        'danger' => 'rejected',
                        'secondary' => 'archived',
                    ]),
                Tables\Columns\TextColumn::make('price_amount')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => $state ? Tour::formatPrice((int) $state, 'EUR') : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('average_rating')
                    ->label('Rating')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1) . ' ★' : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('review_count')
                    ->label('Reviews')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'pending_review' => 'Pending Review',
                        'published' => 'Published',
                        'rejected' => 'Rejected',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Tour')
                    ->modalDescription('This tour will become visible to travelers on the public site.')
                    ->visible(fn (Tour $record) => auth()->user()?->can('publish', $record) && $record->status === 'pending_review')
                    ->action(function (Tour $record) {
                        app(ApproveTourAction::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Tour published')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Tour')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Explain what needs to change for this tour to be approved...'),
                    ])
                    ->visible(fn (Tour $record) => auth()->user()?->can('reject', $record) && $record->status === 'pending_review')
                    ->action(function (Tour $record, array $data) {
                        app(RejectTourAction::class)->execute(auth()->user(), $record, $data);
                        Notification::make()->title('Tour rejected')->warning()->body("Reason: {$data['rejection_reason']}")->send();
                    }),
                Tables\Actions\Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Unpublish Tour')
                    ->modalDescription('This tour will be removed from the public site. Existing bookings will not be affected.')
                    ->visible(fn (Tour $record) => auth()->user()?->can('unpublish', $record) && $record->status === 'published')
                    ->action(function (Tour $record) {
                        app(UnpublishTourAction::class)->execute(auth()->user(), $record);
                        Notification::make()->title('Tour unpublished')->warning()->send();
                    }),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Archive Tour')
                    ->modalDescription('Archiving permanently delists this tour from public search and discovery. The action is irreversible.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Archive Reason')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Explain why this tour is being archived...'),
                    ])
                    ->visible(fn (Tour $record) => auth()->user()?->can('unpublish', $record) && $record->status === 'published')
                    ->action(function (Tour $record, array $data) {
                        app(ArchiveTourAction::class)->execute(auth()->user(), $record, $data['reason']);
                        Notification::make()->title('Tour archived')->danger()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_publish')
                    ->label('Publish Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours'))
                    ->action(function (EloquentCollection $records) {
                        $actor = auth()->user();
                        $failed = 0;
                        foreach ($records as $record) {
                            try {
                                app(ApproveTourAction::class)->execute($actor, $record);
                            } catch (\Throwable) {
                                $failed++;
                            }
                        }
                        Notification::make()
                            ->title($failed ? "{$failed} tour(s) could not be published" : 'Selected tours published')
                            ->{$failed ? 'warning' : 'success'}()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('bulk_reject')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn () => app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours'))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Explain what needs to change for these tours to be approved...'),
                    ])
                    ->action(function (EloquentCollection $records, array $data) {
                        $actor = auth()->user();
                        $failed = 0;
                        foreach ($records as $record) {
                            try {
                                app(RejectTourAction::class)->execute($actor, $record, $data);
                            } catch (\Throwable) {
                                $failed++;
                            }
                        }
                        Notification::make()
                            ->title($failed ? "{$failed} tour(s) could not be rejected" : 'Selected tours rejected')
                            ->{$failed ? 'warning' : 'success'}()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Tour Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('slug')
                            ->label('Slug'),
                        Infolists\Components\TextEntry::make('location')
                            ->label('Location'),
                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Category'),
                        Infolists\Components\TextEntry::make('duration_label')
                            ->label('Duration'),
                        Infolists\Components\TextEntry::make('group_size_min')
                            ->label('Min Group Size'),
                        Infolists\Components\TextEntry::make('group_size_max')
                            ->label('Max Group Size'),
                        Infolists\Components\TextEntry::make('price_amount')
                            ->label('Price')
                            ->formatStateUsing(fn ($state) => $state ? Tour::formatPrice((int) $state, 'EUR') : '—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Performance')
                    ->schema([
                        Infolists\Components\TextEntry::make('bookings_total_count')
                            ->label('Total Bookings')
                            ->getStateUsing(fn (Tour $record): int => $record->bookings()->count()),
                        Infolists\Components\TextEntry::make('bookings_confirmed_count')
                            ->label('Confirmed Bookings')
                            ->getStateUsing(fn (Tour $record): int => $record->bookings()->where('status', 'confirmed')->count()),
                        Infolists\Components\TextEntry::make('bookings_revenue')
                            ->label('Booked Revenue')
                            ->getStateUsing(function (Tour $record): string {
                                $revenue = (int) $record->bookings()
                                    ->whereIn('status', [
                                        Booking::STATUS_CONFIRMED,
                                        Booking::STATUS_COMPLETED,
                                        Booking::STATUS_CANCELLATION_REQUESTED,
                                    ])
                                    ->sum('total_price');

                                return $revenue === 0 ? '—' : (string) Tour::formatPrice($revenue, 'EUR');
                            }),
                    ])
                    ->columns(3)
                    ->collapsible(),
                Infolists\Components\Section::make('Governance Audit')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('governanceAuditLogs')
                            ->schema([
                                Infolists\Components\TextEntry::make('action')
                                    ->label('Action')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('actor.email')
                                    ->label('Admin Actor')
                                    ->placeholder('System'),
                                Infolists\Components\TextEntry::make('after_state.status')
                                    ->label('After Status')
                                    ->badge()
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
                Infolists\Components\Section::make('English source (required)')
                    ->schema([
                        Infolists\Components\TextEntry::make('english_source_title')
                            ->label('Title')
                            ->getStateUsing(fn (Tour $record): ?string => $record->translations->firstWhere('locale', 'en')?->title)
                            ->placeholder('English source missing'),
                        Infolists\Components\TextEntry::make('english_source_description')
                            ->label('Description')
                            ->getStateUsing(fn (Tour $record): ?string => $record->translations->firstWhere('locale', 'en')?->description)
                            ->placeholder('English source missing'),
                        Infolists\Components\TextEntry::make('english_source_itinerary')
                            ->label('Itinerary')
                            ->getStateUsing(function (Tour $record): ?string {
                                $days = $record->translations->firstWhere('locale', 'en')?->itinerary ?? [];

                                return $days === [] ? null : collect($days)
                                    ->map(fn (array $day): string => 'Day ' . $day['day'] . ': ' . $day['title'])
                                    ->implode(' · ');
                            })
                            ->placeholder('No itinerary provided'),
                    ]),
                Infolists\Components\Section::make('AI translations (derived from English)')
                    ->schema([
                        Infolists\Components\TextEntry::make('spanish_translation_status')
                            ->label('Spanish')
                            ->getStateUsing(fn (Tour $record): string => static::translationStatusLabel($record, 'es'))
                            ->badge()
                            ->color(fn (string $state): string => static::translationStatusColor($state)),
                        Infolists\Components\TextEntry::make('italian_translation_status')
                            ->label('Italian')
                            ->getStateUsing(fn (Tour $record): string => static::translationStatusLabel($record, 'it'))
                            ->badge()
                            ->color(fn (string $state): string => static::translationStatusColor($state)),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Status & Dates')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'pending_review' => 'warning',
                                'published' => 'success',
                                'rejected' => 'danger',
                                'archived' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('partner.name')
                            ->label('Partner'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('average_rating')
                            ->label('Rating')
                            ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 1) . ' ★' : 'No reviews'),
                        Infolists\Components\TextEntry::make('review_count')
                            ->label('Reviews'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    private static function translationStatusLabel(Tour $tour, string $locale): string
    {
        return match (app(TourTranslationService::class)->publicStatus($tour, $locale)) {
            'ready' => 'Ready',
            'stale' => 'Outdated',
            'failed' => 'Unavailable',
            default => 'Pending',
        };
    }

    private static function translationStatusColor(string $status): string
    {
        return match ($status) {
            'Ready' => 'success',
            'Outdated' => 'warning',
            'Unavailable' => 'danger',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'create' => Pages\CreateTour::route('/create'),
            'view' => Pages\ViewTour::route('/{record}'),
            'edit' => Pages\EditTour::route('/{record}/edit'),
        ];
    }
}
