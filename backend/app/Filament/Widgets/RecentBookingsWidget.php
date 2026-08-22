<?php

namespace App\Filament\Widgets;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Booking\Models\Booking;
use App\Models\User;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Recent-bookings table on the admin dashboard (Spec 013, US7, FR-013).
 * Read-only list of the 5 most recent bookings. Gated by `view_all_analytics`.
 */
class RecentBookingsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent Bookings';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminAuthorizationService::class)->can($user, 'view_all_analytics');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Booking::query()->with(['traveler', 'tour'])->latest()->limit(5))
            ->paginated(false)
            ->columns([
                TextColumn::make('reference')->label('Reference')->searchable()->weight('bold'),
                TextColumn::make('traveler.name')->label('Traveler'),
                TextColumn::make('tour.slug')->label('Tour')->limit(25),
                TextColumn::make('total_price')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, Booking $record) => Booking::formatPrice((int) $state, $record->currency ?? 'EUR')),
                BadgeColumn::make('status')->label('Status')->colors([
                    'warning' => 'pending_payment',
                    'success' => 'confirmed',
                    'primary' => 'completed',
                    'danger' => 'cancelled',
                    'gray' => 'expired',
                ]),
                TextColumn::make('created_at')->label('Booked')->dateTime('M j, Y H:i'),
            ]);
    }
}
