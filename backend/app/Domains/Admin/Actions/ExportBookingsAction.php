<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Booking\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export bookings to CSV (Admin 2.0, T-B03).
 *
 * Streams a UTF-8 BOM + CSV of bookings honoring the admin's filters, capped
 * at a hard maximum to bound memory. Requires `manage_bookings` OR
 * `export_financial_reports`. Writes an immutable `booking.export` governance
 * audit entry recording which filters were applied and how many rows left
 * the platform.
 */
class ExportBookingsAction
{
    public const MAX_ROWS = 10000;

    public function __construct(
        private readonly GovernanceAuditService $audit,
    ) {}

    public function toCsv(User $actor, array $filters = []): string
    {
        abort_unless(self::isAuthorized($actor), 403, 'You are not authorized to export bookings.');

        $columns = self::columns();
        $rowCount = 0;

        $handle = fopen('php://temp', 'rb+');
        try {
            fputcsv($handle, array_keys($columns));

            $this->foreachRow($actor, $filters, function (Booking $booking) use ($handle, $columns, &$rowCount): void {
                $rowCount++;
                $values = [];
                foreach (array_keys($columns) as $key) {
                    $values[] = match ($key) {
                        'traveler_email' => (string) ($booking->traveler->email ?? ''),
                        'tour_title' => (string) ($booking->tour?->displayTitle() ?? $booking->tour->slug ?? ''),
                        'total_price' => Tour::formatPrice((int) $booking->total_price, (string) $booking->currency),
                        'status' => (string) $booking->status,
                        'created_at' => (string) optional($booking->created_at)->toDateTimeString(),
                        'tour_date' => (string) optional($booking->tour_date)->toDateString(),
                        'participant_count' => (int) $booking->participant_count,
                        'reference' => (string) $booking->reference,
                        default => '',
                    };
                }
                fputcsv($handle, $values);
            });

            rewind($handle);

            $this->audit->log(
                $actor,
                'booking.export',
                null,
                null,
                null,
                ['filters' => $filters, 'row_count' => $rowCount],
                'booking',
            );

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    public function download(User $actor, array $filters = []): StreamedResponse
    {
        $csv = $this->toCsv($actor, $filters);

        return response()->streamDownload(function () use ($csv): void {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens the CSV cleanly.
            echo $csv;
        }, $this->filename(), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Ordered, filter-scoped booking query with the relationships used by
     * the export columns, capped at MAX_ROWS.
     */
    private function query(User $actor, array $filters): Builder
    {
        $q = Booking::query()
            ->with(['traveler:id,email', 'tour'])
            ->limit(self::MAX_ROWS);

        $q->when(! empty($filters['from']), fn (Builder $query) => $query->where('created_at', '>=', $filters['from']));

        $q->when(! empty($filters['to']), fn (Builder $query) => $query->where('created_at', '<=', $filters['to']));

        $q->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', (string) $filters['status']));

        $q->when(! empty($filters['partner_id']), fn (Builder $query) => $query->whereHas('tour', fn (Builder $tour) => $tour->where('partner_id', $filters['partner_id'])));

        return $q->limit(self::MAX_ROWS);
    }

    private function filename(): string
    {
        return 'bookings-export-' . now()->format('Ymd-His') . '.csv';
    }

    public static function isAuthorized(User $user): bool
    {
        if ($user->role !== 'admin' || ($user->status ?? 'active') !== 'active') {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_bookings')
            || $authz->can($user, 'export_financial_reports');
    }

    /**
     * @return array<string, string> header => description (used by tests
     *                               and by BookingResource's export header action).
     */
    public static function columns(): array
    {
        return [
            'reference' => 'Booking reference',
            'traveler_email' => 'Traveler email',
            'tour_title' => 'Tour title',
            'tour_date' => 'Tour date',
            'participant_count' => 'Participants',
            'total_price' => 'Total price',
            'status' => 'Booking status',
            'created_at' => 'Booked at',
        ];
    }

    private function foreachRow(User $actor, array $filters, callable $rowHandler): void
    {
        $this->query($actor, $filters)
            ->cursor()
            ->each($rowHandler);
    }
}
