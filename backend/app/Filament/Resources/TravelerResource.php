<?php

namespace App\Filament\Resources;

use App\Domains\Admin\Services\TravelerModerationService;
use App\Domains\Booking\Models\Booking;
use App\Filament\Resources\TravelerResource\Pages;
use App\Models\Tour;
use App\Models\User;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Traveler account management (Admin 2.0, T-B02).
 *
 * Read-mostly list + view of traveler accounts with booking/payment/review
 * history summaries and the suspend/reactivate governance actions. Scoped
 * to `users.role = 'traveler'`; authorized for admins holding
 * `manage_travelers` OR `manage_users`.
 */
class TravelerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Marketplace Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'travelers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'suspended',
                    ])
                    ->formatStateUsing(fn (?string $state): string => $state === null || $state === '' ? 'active' : $state),
                Tables\Columns\TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->counts('bookings')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_spend')
                    ->label('Total Spend')
                    ->getStateUsing(fn (User $record): string => \App\Models\Tour::formatPrice(
                        (int) $record->bookings()->whereIn('status', [
                            \App\Domains\Booking\Models\Booking::STATUS_CONFIRMED,
                            \App\Domains\Booking\Models\Booking::STATUS_COMPLETED,
                            \App\Domains\Booking\Models\Booking::STATUS_CANCELLATION_REQUESTED,
                        ])->sum('total_price'),
                        'EUR',
                    )),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Traveler')
                    ->modalDescription('Suspension immediately revokes every active API token for this traveler.')
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->required()
                            ->maxLength(500)
                            ->placeholder('Explain why this traveler is being suspended...'),
                    ])
                    ->visible(fn (User $record) => TravelerModerationService::isAuthorized(static::currentActor())
                        && $record->role === 'traveler'
                        && $record->status !== 'suspended')
                    ->action(function (User $record, array $data) {
                        app(TravelerModerationService::class)->suspend(static::currentActor(), $record, $data['reason']);
                        Notification::make()->title('Traveler suspended')->warning()->send();
                    }),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Reactivate Traveler')
                    ->visible(fn (User $record) => TravelerModerationService::isAuthorized(static::currentActor())
                        && $record->role === 'traveler'
                        && $record->status === 'suspended')
                    ->action(function (User $record) {
                        app(TravelerModerationService::class)->reactivate(static::currentActor(), $record);
                        Notification::make()->title('Traveler reactivated')->success()->send();
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        /** @var array<int, string> $revenueStatuses */
        $revenueStatuses = [
            \App\Domains\Booking\Models\Booking::STATUS_CONFIRMED,
            \App\Domains\Booking\Models\Booking::STATUS_COMPLETED,
            \App\Domains\Booking\Models\Booking::STATUS_CANCELLATION_REQUESTED,
        ];

        return $infolist
            ->schema([
                Infolists\Components\Section::make('Traveler Profile')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('Phone')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('preferred_currency')
                            ->label('Preferred Currency')
                            ->default('EUR'),
                        Infolists\Components\TextEntry::make('locale')
                            ->label('Locale')
                            ->default('en'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Joined')
                            ->dateTime(),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Booking History Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('booking_total_count')
                            ->label('Total Bookings')
                            ->default(fn (User $record): int => $record->bookings()->count()),
                        Infolists\Components\TextEntry::make('booking_confirmed_count')
                            ->label('Confirmed + Completed')
                            ->default(fn (User $record): int => $record->bookings()->whereIn('status', ['confirmed', 'completed'])->count()),
                        Infolists\Components\TextEntry::make('booking_total_spend')
                            ->label('Lifetime Spend')
                            ->default(function (User $record): string {
                                $revenue = (int) $record->bookings()->whereIn('status', [
                                    Booking::STATUS_CONFIRMED,
                                    Booking::STATUS_COMPLETED,
                                    Booking::STATUS_CANCELLATION_REQUESTED,
                                ])->sum('total_price');

                                return Tour::formatPrice($revenue, 'EUR');
                            }),
                        Infolists\Components\TextEntry::make('booking_last_date')
                            ->label('Latest Booking')
                            ->default(fn (User $record): ?string => $record->bookings()->latest('created_at')->value('created_at')?->format('M j, Y H:i'))
                            ->placeholder('No bookings yet'),
                        Infolists\Components\TextEntry::make('payment_intent_count')
                            ->label('Paid Payments')
                            ->default(fn (User $record): int => $record->bookings()
                                ->whereHas('payment', fn ($q) => $q->where('status', 'succeeded'))
                                ->count()),
                        Infolists\Components\TextEntry::make('latest_payment_amount')
                            ->label('Latest Payment Amount')
                            ->default(function (User $record): ?string {
                                /** @var \App\Domains\Payment\Models\Payment|null $payment */
                                $payment = \App\Domains\Payment\Models\Payment::query()
                                    ->whereHas('booking', fn ($q) => $q->where('traveler_id', $record->id))
                                    ->latest('id')
                                    ->first();

                                return $payment === null ? null : \App\Models\Tour::formatPrice((int) $payment->amount, $payment->currency);
                            })
                            ->placeholder('No payments yet'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Reviews Submitted')
                    ->schema([
                        Infolists\Components\TextEntry::make('reviews_total_count')
                            ->label('Reviews Submitted')
                            ->default(fn (User $record): int => $record->reviews()->count()),
                        Infolists\Components\TextEntry::make('reviews_flagged_count')
                            ->label('Flagged Reviews')
                            ->default(fn (User $record): int => $record->reviews()->where('status', 'flagged')->count()),
                        Infolists\Components\TextEntry::make('reviews_average_rating')
                            ->label('Average Rating Given')
                            ->default(fn (User $record): ?float => round((float) ($record->reviews()->avg('rating') ?? 0), 2))
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Infolists\Components\Section::make('Account Status & Suspension Info')
                    ->schema([
                        Infolists\Components\TextEntry::make('status')
                            ->label('Account Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'suspended' => 'danger',
                                default => 'success',
                            }),
                        Infolists\Components\TextEntry::make('suspended_at')
                            ->label('Suspended At')
                            ->dateTime()
                            ->placeholder('—')
                            ->visible(fn (User $record): bool => $record->status === 'suspended'),
                        Infolists\Components\TextEntry::make('suspension_reason')
                            ->label('Suspension Reason')
                            ->default(fn (User $record): ?string => $record->status === 'suspended' ? $record->suspension_reason : null)
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('sanctum_tokens_count')
                            ->label('Active API Tokens')
                            ->default(fn (User $record): int => $record->tokens()->count()),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function canViewAny(): bool
    {
        $user = static::currentActor();

        return $user !== null && TravelerModerationService::isAuthorized($user);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    private static function currentActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'traveler');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTravelers::route('/'),
            'view' => Pages\ViewTraveler::route('/{record}'),
        ];
    }
}
