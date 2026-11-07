<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Domains\Admin\Actions\ExportBookingsAction;
use App\Filament\Resources\BookingResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\IconPosition;

/**
 * Bookings list page (Admin 2.0, T-B03).
 *
 * Adds the `export_csv` header action on top of the Spec 013 list page:
 * optionally filters (date range + status) and streams the bookings CSV,
 * writing the `booking.export` governance audit entry through
 * ExportBookingsAction.
 */
class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    private const STATUS_OPTIONS = [
        'pending_payment' => 'Pending Payment',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'no_show' => 'No Show',
    ];

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->iconPosition(IconPosition::Before)
                ->modalHeading('Export Bookings CSV')
                ->modalDescription('Exports up to 10,000 bookings matching the chosen filters as CSV. The export scope is recorded in the governance audit log.')
                ->form([
                    Select::make('status')
                        ->label('Status')
                        ->options(self::STATUS_OPTIONS)
                        ->nullable(),
                    DatePicker::make('from')
                        ->label('Booked From'),
                    DatePicker::make('to')
                        ->label('Booked To'),
                ])
                ->visible(fn () => auth()->user() instanceof User
                    && ExportBookingsAction::isAuthorized(auth()->user()))
                ->action(function (array $data) {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $filters = array_filter([
                        'status' => $data['status'] ?? null,
                        'from' => $data['from'] ?? null,
                        'to' => $data['to'] ?? null,
                    ], fn ($value) => $value !== null && $value !== '');

                    return app(ExportBookingsAction::class)->download($actor, $filters);
                }),
        ];
    }
}
