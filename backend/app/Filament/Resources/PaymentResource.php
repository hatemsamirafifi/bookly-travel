<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only payments & refunds viewer (Admin 2.0, T-C02).
 *
 * Replaces recorded card digits where PCI rules forbid full display; card
 * data comes only from Stripe. No create/edit/delete — refunds/captures are
 * written by Spec 008 webhook flows, never by the admin surface.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payments & Refunds';

    protected static ?string $pluralLabel = 'Payments & Refunds';

    protected static ?string $slug = 'payments';

    protected static ?string $recordTitleAttribute = 'stripe_payment_intent_id';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return self::canViewFinancials(auth()->user());
    }

    public static function canGloballySearch(): bool
    {
        return self::canViewAny();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['stripe_payment_intent_id', 'stripe_refund_id', 'booking.reference'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->stripe_payment_intent_id;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Status' => $record->status,
            'Booking' => $record->booking?->reference,
        ];
    }

    /**
     * Finance readers hold `view_finances` OR the analytics-wide
     * `view_all_analytics` flag.
     */
    public static function canViewFinancials(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'view_finances')
            || $authz->can($user, 'view_all_analytics');
    }

    public static function canView(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('booking:id,reference');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking.reference')
                    ->label('Booking')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stripe_payment_intent_id')
                    ->label('Stripe Payment Intent')
                    ->limit(24)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stripe_refund_id')
                    ->label('Stripe Refund ID')
                    ->placeholder('—')
                    ->limit(24)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'primary' => fn ($state) => $state === 'capture',
                        'warning' => fn ($state) => $state === 'refund',
                        'gray' => fn ($state) => ! in_array($state, ['capture', 'refund'], true),
                    ]),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (Payment $record, int $state): string => Booking::formatPrice(
                        $state,
                        (string) $record->currency,
                    ))
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => fn ($state) => $state === 'succeeded',
                        'danger' => fn ($state) => $state === 'failed',
                        'warning' => fn ($state) => in_array($state, ['pending', 'requires_action'], true),
                        'gray' => fn ($state) => ! in_array($state, ['succeeded', 'failed', 'pending', 'requires_action'], true),
                    ])
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_brand')
                    ->label('Card Brand')
                    ->placeholder('—')
                    ->badge()
                    ->color('secondary')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('card_last_four')
                    ->label('Card Last Four')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Recorded At')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'capture' => 'Capture',
                        'refund' => 'Refund',
                        'charge' => 'Charge (legacy)',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'succeeded' => 'Succeeded',
                        'failed' => 'Failed',
                        'pending' => 'Pending',
                        'requires_action' => 'Requires Action',
                    ]),
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
                Infolists\Components\Section::make('Payment')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')->label('ID'),
                        Infolists\Components\TextEntry::make('booking.reference')->label('Booking Reference')->url(fn (Payment $record): string => BookingResource::getUrl('view', ['record' => $record->booking_id])),
                        Infolists\Components\TextEntry::make('stripe_payment_intent_id')->label('Stripe Payment Intent')->copyable(),
                        Infolists\Components\TextEntry::make('stripe_refund_id')->label('Stripe Refund ID')->placeholder('—'),
                        Infolists\Components\TextEntry::make('type')->label('Type')->badge()->color(fn ($state) => match (true) {
                            $state === 'capture' => 'primary',
                            $state === 'refund' => 'warning',
                            default => 'gray',
                        }),
                        Infolists\Components\TextEntry::make('status')->badge(),
                        Infolists\Components\TextEntry::make('amount')
                            ->formatStateUsing(fn (Payment $record, int $state): string => Booking::formatPrice(
                                $state,
                                (string) $record->currency,
                            )),
                        Infolists\Components\TextEntry::make('currency')->label('Currency')->badge(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Card')
                    ->schema([
                        Infolists\Components\TextEntry::make('card_brand')->label('Card Brand')->placeholder('—'),
                        Infolists\Components\TextEntry::make('card_last_four')->label('Card Last Four')->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Infolists\Components\Section::make('Record')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')->label('Recorded At')->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')->label('Updated At')->dateTime()->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
