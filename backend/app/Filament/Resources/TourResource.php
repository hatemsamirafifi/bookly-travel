<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Actions\RejectTourAction;
use App\Domains\Admin\Actions\UnpublishTourAction;
use App\Domains\Admin\Services\AdminAuthorizationService;
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

class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Platform';

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
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
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Tour')
                    ->modalDescription('This tour will become visible to travelers on the public site.')
                    ->visible(fn (Tour $record) => auth()->user()?->can('publish', $record) && in_array($record->status, ['pending_review', 'rejected']))
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
                            ->{$failed ? 'warning' : 'warning'}()
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
                Infolists\Components\Section::make('Translations')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('translations')
                            ->schema([
                                Infolists\Components\TextEntry::make('locale')
                                    ->label('Language')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('title')
                                    ->label('Title'),
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Description')
                                    ->limit(200),
                            ])
                            ->columns(3),
                    ]),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTours::route('/'),
            'view' => Pages\ViewTour::route('/{record}'),
        ];
    }
}
