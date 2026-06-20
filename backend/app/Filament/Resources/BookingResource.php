<?php

namespace App\Filament\Resources;

use App\Domains\Booking\Models\Booking;
use App\Filament\Resources\BookingResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
