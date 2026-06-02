<?php

namespace App\Domains\Partner\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get aggregated analytics summary for a partner.
     *
     * Returns total bookings, total revenue, average rating, and conversion rate
     * scoped to the partner's tours and optionally filtered by date range and tour.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{date_from?: string, date_to?: string, tour_id?: int} $filters
     * @return array{total_bookings: int, total_revenue: int, average_rating: float, conversion_rate: float}
     */
    public function getSummary(int $partnerId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        $tourIds = $this->getPartnerTourIds($partnerId, $filters['tour_id'] ?? null);

        // Total bookings
        $totalBookings = Booking::whereIn('tour_id', $tourIds)
            ->whereBetween('tour_date', [$dateFrom, $dateTo])
            ->count();

        // Total revenue (only completed or confirmed bookings)
        $totalRevenue = (int) Booking::whereIn('tour_id', $tourIds)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->whereBetween('tour_date', [$dateFrom, $dateTo])
            ->sum('total_price');

        // Average rating across partner's tours
        $averageRating = (float) Review::whereIn('tour_id', $tourIds)
            ->whereIn('status', ['visible', 'flagged'])
            ->when($dateFrom, fn ($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_at', '<=', $dateTo . ' 23:59:59'))
            ->avg('rating') ?? 0.0;

        // Conversion rate: bookings / tour views (placeholder until view tracking is implemented)
        // Currently returns 0.0 since tour_views tracking is not yet in the database
        $conversionRate = 0.0;

        return [
            'total_bookings' => $totalBookings,
            'total_revenue' => $totalRevenue,
            'average_rating' => round($averageRating, 2),
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Get bookings-over-time chart data grouped by day or week.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{date_from?: string, date_to?: string, tour_id?: int, granularity?: string} $filters
     * @return array<int, array{date: string, bookings: int, revenue: int}>
     */
    public function getBookingsOverTime(int $partnerId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();
        $granularity = $filters['granularity'] ?? 'day';

        $tourIds = $this->getPartnerTourIds($partnerId, $filters['tour_id'] ?? null);

        $dateFormat = $granularity === 'week'
            ? 'IW' // ISO week
            : 'YYYY-MM-DD';

        $rows = Booking::whereIn('tour_id', $tourIds)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->whereBetween('tour_date', [$dateFrom, $dateTo])
            ->selectRaw(
                "TO_CHAR(tour_date, '{$dateFormat}') AS period, " .
                'COUNT(*) AS bookings, ' .
                'COALESCE(SUM(total_price), 0) AS revenue'
            )
            ->groupByRaw("TO_CHAR(tour_date, '{$dateFormat}')")
            ->orderBy('period')
            ->get();

        if ($granularity === 'day') {
            // Fill gaps in the date range with zero values
            return $this->fillDateGaps($rows, $dateFrom, $dateTo);
        }

        return $rows->map(fn ($row) => [
            'date' => $row->period,
            'bookings' => (int) $row->bookings,
            'revenue' => (int) $row->revenue,
        ])->values()->all();
    }

    /**
     * Get the full analytics payload including summary and chart data.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param array{date_from?: string, date_to?: string, tour_id?: int, granularity?: string} $filters
     * @return array<string, mixed>
     */
    public function getAnalytics(int $partnerId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();

        return [
            'summary' => $this->getSummary($partnerId, $filters),
            'bookings_over_time' => $this->getBookingsOverTime($partnerId, $filters),
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ];
    }

    /**
     * Get the tour IDs for a partner, optionally filtered by a specific tour.
     *
     * @param int $partnerId The authenticated partner's ID
     * @param int|null $tourId Optional tour ID filter
     * @return \Illuminate\Support\Collection<int, int>
     */
    protected function getPartnerTourIds(int $partnerId, ?int $tourId = null)
    {
        $query = Tour::where('partner_id', $partnerId);

        if ($tourId) {
            $query->where('id', $tourId);
        }

        return $query->pluck('id');
    }

    /**
     * Fill gaps in daily chart data to ensure every date in the range is present.
     *
     * @param \Illuminate\Support\Collection $rows The aggregated rows
     * @param string $dateFrom Start date (Y-m-d)
     * @param string $dateTo End date (Y-m-d)
     * @return array<int, array{date: string, bookings: int, revenue: int}>
     */
    protected function fillDateGaps($rows, string $dateFrom, string $dateTo): array
    {
        $rowsByKey = $rows->keyBy('period');

        $period = new \DatePeriod(
            new \DateTime($dateFrom),
            new \DateInterval('P1D'),
            (new \DateTime($dateTo))->modify('+1 day')
        );

        $result = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $row = $rowsByKey->get($key);

            $result[] = [
                'date' => $key,
                'bookings' => $row ? (int) $row->bookings : 0,
                'revenue' => $row ? (int) $row->revenue : 0,
            ];
        }

        return $result;
    }
}