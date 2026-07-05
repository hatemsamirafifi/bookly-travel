<?php

namespace App\Filament\Resources;

use App\Domains\Reviews\Actions\HideReviewAction;
use App\Domains\Reviews\Actions\ReinstateReviewAction;
use App\Domains\Reviews\Models\Review;
use App\Enums\ReviewStatus;
use App\Filament\Resources\ReviewResource\Pages;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'flagged')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('tour.slug')
                    ->label('Tour')
                    ->searchable()
                    ->limit(30)
                    ->sortable(),
                Tables\Columns\TextColumn::make('traveler.name')
                    ->label('Traveler')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->alignCenter()
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'visible',
                        'gray' => 'hidden',
                        'danger' => 'flagged',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'visible' => 'Visible',
                        'hidden' => 'Hidden',
                        'flagged' => 'Flagged',
                    ]),
                Tables\Filters\SelectFilter::make('tour')
                    ->relationship('tour', 'slug'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('Submitted From'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('Submitted To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['date_to'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\TernaryFilter::make('flagged')
                    ->label('Flagged only')
                    ->placeholder('All')
                    ->trueLabel('Flagged')
                    ->falseLabel('Not flagged')
                    ->queries(
                        fn ($q) => $q->where('status', 'flagged'),
                        fn ($q) => $q->where('status', '!=', 'flagged'),
                        fn ($q) => $q,
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('hide')
                    ->label('Hide')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Hide Review')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (Review $record) => auth()->user()?->can('manage', $record) && $record->canTransitionTo(ReviewStatus::Hidden))
                    ->action(function (Review $record, array $data) {
                        app(HideReviewAction::class)->execute($record, (int) auth()->id(), $data['reason']);
                        Notification::make()->title('Review hidden')->warning()->send();
                    }),
                Tables\Actions\Action::make('reinstate')
                    ->label('Reinstate')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reinstate Review')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (Review $record) => auth()->user()?->can('manage', $record) && $record->canTransitionTo(ReviewStatus::Visible))
                    ->action(function (Review $record, array $data) {
                        app(ReinstateReviewAction::class)->execute($record, (int) auth()->id(), $data['reason']);
                        Notification::make()->title('Review reinstated')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_hide')
                    ->label('Hide Selected')
                    ->icon('heroicon-o-eye-slash')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->can('manage', new Review()))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (EloquentCollection $records, array $data) {
                        $adminId = (int) auth()->id();
                        $failed = 0;
                        foreach ($records as $record) {
                            try {
                                app(HideReviewAction::class)->execute($record, $adminId, $data['reason']);
                            } catch (\Throwable) {
                                $failed++;
                            }
                        }
                        Notification::make()
                            ->title($failed ? "{$failed} review(s) could not be hidden" : 'Selected reviews hidden')
                            ->{$failed ? 'warning' : 'warning'}()
                            ->send();
                    }),
                Tables\Actions\BulkAction::make('bulk_reinstate')
                    ->label('Reinstate Selected')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->can('manage', new Review()))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (EloquentCollection $records, array $data) {
                        $adminId = (int) auth()->id();
                        $failed = 0;
                        foreach ($records as $record) {
                            try {
                                app(ReinstateReviewAction::class)->execute($record, $adminId, $data['reason']);
                            } catch (\Throwable) {
                                $failed++;
                            }
                        }
                        Notification::make()
                            ->title($failed ? "{$failed} review(s) could not be reinstated" : 'Selected reviews reinstated')
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
                Infolists\Components\Section::make('Review')
                    ->schema([
                        Infolists\Components\TextEntry::make('tour.slug')->label('Tour'),
                        Infolists\Components\TextEntry::make('traveler.name')->label('Traveler'),
                        Infolists\Components\TextEntry::make('traveler.email')->label('Email')->copyable(),
                        Infolists\Components\TextEntry::make('rating')->label('Rating')->badge(),
                        Infolists\Components\TextEntry::make('status')->label('Status')->badge()
                            ->color(fn (string $state) => match ($state) {
                                'visible' => 'success',
                                'hidden' => 'gray',
                                'flagged' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('comment')->label('Comment')->wrap(),
                        Infolists\Components\TextEntry::make('created_at')->label('Submitted')->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Moderation Audit')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('auditTrails')
                            ->schema([
                                Infolists\Components\TextEntry::make('action')->label('Action')->badge(),
                                Infolists\Components\TextEntry::make('reason')->label('Reason')->placeholder('—'),
                                Infolists\Components\TextEntry::make('created_at')->label('When')->dateTime(),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
        ];
    }
}