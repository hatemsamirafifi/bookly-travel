<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Actions\TransitionBookingStatusAction;
use App\Domains\Booking\Models\Booking;
use App\Filament\Resources\BookingResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('traveler.name')
                    ->label('Traveler')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tour.slug')
                    ->label('Tour')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('tour_date')
                    ->label('Tour Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('participant_count')
                    ->label('Guests')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, Booking $record) => Booking::formatPrice((int) $state, $record->currency ?? 'EUR'))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending_payment',
                        'success' => 'confirmed',
                        'primary' => 'completed',
                        'danger' => 'cancelled',
                        'gray' => 'expired',
                        'secondary' => 'no_show',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Booked At')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_payment' => 'Pending Payment',
                        'confirmed' => 'Confirmed',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'expired' => 'Expired',
                        'no_show' => 'No Show',
                    ]),
                Tables\Filters\Filter::make('tour_date')
                    ->form([
                        DatePicker::make('date_from')
                            ->label('Tour Date From'),
                        DatePicker::make('date_to')
                            ->label('Tour Date To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['date_from'], fn ($q, $date) => $q->whereDate('tour_date', '>=', $date))
                            ->when($data['date_to'], fn ($q, $date) => $q->whereDate('tour_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_completed')
                    ->label('Mark Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record) => auth()->user()?->can('transition', $record) && $record->canTransitionTo(Booking::STATUS_COMPLETED))
                    ->action(function (Booking $record) {
                        app(TransitionBookingStatusAction::class)->execute(auth()->user(), $record, Booking::STATUS_COMPLETED);
                        Notification::make()->title('Booking marked completed')->success()->send();
                    }),
                Tables\Actions\Action::make('mark_no_show')
                    ->label('Mark No-Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record) => auth()->user()?->can('transition', $record) && $record->canTransitionTo(Booking::STATUS_NO_SHOW))
                    ->action(function (Booking $record) {
                        app(TransitionBookingStatusAction::class)->execute(auth()->user(), $record, Booking::STATUS_NO_SHOW);
                        Notification::make()->title('Booking marked no-show')->warning()->send();
                    }),
                Tables\Actions\Action::make('mark_expired')
                    ->label('Mark Expired')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record) => auth()->user()?->can('transition', $record) && $record->canTransitionTo(Booking::STATUS_EXPIRED))
                    ->action(function (Booking $record) {
                        app(TransitionBookingStatusAction::class)->execute(auth()->user(), $record, Booking::STATUS_EXPIRED);
                        Notification::make()->title('Booking marked expired')->warning()->send();
                    }),
                Tables\Actions\Action::make('request_cancellation')
                    ->label('Request Cancellation')
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Request Cancellation')
                    ->modalDescription('Flags this booking as cancellation-requested. The refund is processed by the payment domain (Spec 008); this records the status and audit only.')
                    ->visible(fn (Booking $record) => auth()->user()?->can('transition', $record) && $record->canTransitionTo(Booking::STATUS_CANCELLATION_REQUESTED))
                    ->action(function (Booking $record) {
                        app(TransitionBookingStatusAction::class)->execute(auth()->user(), $record, Booking::STATUS_CANCELLATION_REQUESTED);
                        Notification::make()->title('Cancellation requested — refund routed to payments')->warning()->send();
                    }),
                Tables\Actions\Action::make('cancel')
                    ->label('Cancel Booking')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Booking')
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->visible(fn (Booking $record) => auth()->user()?->can('transition', $record) && $record->canTransitionTo(Booking::STATUS_CANCELLED))
                    ->action(function (Booking $record, array $data) {
                        app(TransitionBookingStatusAction::class)->execute(auth()->user(), $record, Booking::STATUS_CANCELLED);
                        $record->update(['cancellation_reason' => $data['cancellation_reason']]);
                        Notification::make()->title('Booking cancelled — refund routed to payments')->danger()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Booking Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('reference')
                            ->label('Reference')
                            ->copyable()
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending_payment' => 'warning',
                                'confirmed' => 'success',
                                'completed' => 'primary',
                                'cancelled' => 'danger',
                                'expired' => 'gray',
                                'no_show' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('traveler.name')
                            ->label('Traveler'),
                        Infolists\Components\TextEntry::make('traveler.email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('tour.slug')
                            ->label('Tour'),
                        Infolists\Components\TextEntry::make('tour_date')
                            ->label('Tour Date')
                            ->date(),
                        Infolists\Components\TextEntry::make('participant_count')
                            ->label('Participants'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Financial')
                    ->schema([
                        Infolists\Components\TextEntry::make('price_per_person')
                            ->label('Price / Person')
                            ->formatStateUsing(fn ($state, Booking $record) => Booking::formatPrice((int) $state, $record->currency ?? 'EUR')),
                        Infolists\Components\TextEntry::make('total_price')
                            ->label('Total')
                            ->formatStateUsing(fn ($state, Booking $record) => Booking::formatPrice((int) $state, $record->currency ?? 'EUR')),
                        Infolists\Components\TextEntry::make('currency')
                            ->label('Currency'),
                        Infolists\Components\TextEntry::make('stripe_payment_intent_id')
                            ->label('Stripe Payment Intent')
                            ->copyable()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('payment_confirmed_at')
                            ->label('Payment Confirmed')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Lifecycle')
                    ->schema([
                        Infolists\Components\TextEntry::make('idempotency_key')
                            ->label('Idempotency Key')
                            ->copyable()
                            ->toggleable(isToggledHiddenByDefault: true),
                        Infolists\Components\TextEntry::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('cancelled_at')
                            ->label('Cancelled At')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('pending_expires_at')
                            ->label('Payment Expiry')
                            ->dateTime()
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('anonymized_at')
                            ->label('Anonymized At')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Audit Trail')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('auditLogs')
                            ->schema([
                                Infolists\Components\TextEntry::make('action')
                                    ->label('Action')
                                    ->badge(),
                                Infolists\Components\TextEntry::make('actor_description')
                                    ->label('Actor'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                    ])
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
                                Infolists\Components\TextEntry::make('metadata.financial')
                                    ->label('Financial')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime(),
                            ])
                            ->columns(5),
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
            'index' => Pages\ListBookings::route('/'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }
}
