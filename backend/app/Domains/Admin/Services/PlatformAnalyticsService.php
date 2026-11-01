<?php

namespace App\Domains\Admin\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Domains\Payment\Services\ApplicationFeeCalculator;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Admin marketplace analytics (Admin 2.0, T-B01).
 *
 * Redis-cached KPI snapshot + trend series + top rankings for the Filament
 * dashboard and admin reporting pages. Every public entrypoint takes the
 * standard date-range presets (`today`, `yesterday`, `7d`, `30d`, `90d`,
 * `custom`) and funnels them through `resolveDateRange()` so all series share
 * one range contract. KPI snapshots are cached for 120 seconds under a
 * `admin:analytics:kpis:{preset}:{from}:{to}` key.
 */
class PlatformAnalyticsService
{
    public const CACHE_TTL_SECONDS = 120;

    public const PRESETS = ['today', 'yesterday', '7d', '30d', '90d', 'custom'];

    private const REVENUE_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_COMPLETED,
        Booking::STATUS_CANCELLATION_REQUESTED,
    ];

    /**
     * Resolve a preset into a concrete inclusive date range.
     *
     * @return array{0: Carbon, 1: Carbon} [from, to] with from at startOfDay
     *                                     and to at endOfDay.
     */
    public function resolveDateRange(string $preset = '30d', ?string $from = null, ?string $to = null): array
    {
        return match ($preset) {
            'today' => [Carbon::today()->copy(), Carbon::today()->copy()],
            'yesterday' => [Carbon::yesterday()->copy(), Carbon::yesterday()->copy()],
            '7d' => [Carbon::today()->copy()->subDays(6), Carbon::today()->copy()],
            '90d' => [Carbon::today()->copy()->subDays(89), Carbon::today()->copy()],
            'custom' => $this->resolveCustomRange($from, $to),
            default => $this->resolveDefault(),
        };
    }

    private function resolveDefault(): array
    {
        return [Carbon::today()->copy()->subDays(29), Carbon::today()->copy()];
    }

    private function resolveCustomRange(?string $from, ?string $to): array
    {
        $fromDate = $this->parseDay($from);
        $toDate = $this->parseDay($to);

        if ($fromDate === null || $toDate === null) {
            return $this->resolveDefault();
        }

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate];
    }

    private function parseDay(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, int|float>
     */
    public function kpis(string $preset = '30d', ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->resolveDateRange($preset, $from, $to);
        $key = sprintf('admin:analytics:kpis:%s:%s:%s', $preset, $start->toDateString(), $end->toDateString());

        /** @var array<string, int|float> $cached */
        $cached = Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($start, $end, $preset): array {
            return $this->computeKpis($start, $end, $preset);
        });

        return $cached;
    }

    /**
     * @return array{labels: list<string>, bookings: list<int>, revenue_cents: list<int>}
     */
    public function bookingAndRevenueTrends(string $preset = '30d', ?string $from = null, ?string $to = null): array
    {
        [$start, $end] = $this->resolveDateRange($preset, $from, $to);

        $rows = Booking::query()
            ->whereBetween('created_at', [$start->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw("DATE(created_at) as day, COUNT(*) as bookings, COALESCE(SUM(CASE WHEN status IN ('confirmed','completed','cancellation_requested') THEN total_price ELSE 0 END), 0) as revenue")
            ->groupBy('day')
            ->get();

        $bookings = $rows->pluck('bookings', 'day');
        $revenue = $rows->pluck('revenue', 'day');

        $labels = [];
        $bookingSeries = [];
        $revenueSeries = [];

        for ($cursor = $start->copy(); $cursor <= $end->copy(); $cursor->addDay()) {
            $day = $cursor->toDateString();
            $labels[] = $day;
            $bookingSeries[] = (int) ($bookings[$day] ?? 0);
            $revenueSeries[] = (int) ($revenue[$day] ?? 0);
        }

        return ['labels' => $labels, 'bookings' => $bookingSeries, 'revenue_cents' => $revenueSeries];
    }

    private const REVENUE_CASE_SQL = "COALESCE(SUM(CASE WHEN bookings.status IN ('confirmed','completed','cancellation_requested') THEN bookings.total_price ELSE 0 END), 0)";

    /**
     * @return list<array<string, int|string|null>>
     */
    public function topTours(int $limit = 5): array
    {
        $rows = Booking::query()
            ->selectRaw('tours.id as tour_id, tours.slug, tour_translations.title, COUNT(*) as bookings, '.self::REVENUE_CASE_SQL.' as revenue')
            ->join('tours', 'tours.id', '=', 'bookings.tour_id')
            ->join('tour_translations', function ($join) {
                $join->on('tour_translations.tour_id', '=', 'tours.id')
                    ->where('tour_translations.locale', 'en');
            })
            ->groupBy('tours.id', 'tours.slug', 'tour_translations.title')
            ->orderByDesc('bookings')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'tour_id' => (int) $row->tour_id,
            'slug' => (string) $row->slug,
            'title' => (string) ($row->title ?? $row->slug),
            'bookings' => (int) $row->bookings,
            'revenue_cents' => (int) $row->revenue,
        ])->all();
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function topPartners(int $limit = 5): array
    {
        $rows = Booking::query()
            ->selectRaw('partners.id as partner_id, partner_profiles.company_name, users.name as contact_name, COUNT(*) as bookings, '.self::REVENUE_CASE_SQL.' as revenue')
            ->join('tours', 'tours.id', '=', 'bookings.tour_id')
            ->join('partners', 'partners.id', '=', 'tours.partner_id')
            ->leftJoin('partner_profiles', 'partner_profiles.partner_id', '=', 'partners.id')
            ->leftJoin('users', 'users.id', '=', 'partners.user_id')
            ->groupBy('partners.id', 'partner_profiles.company_name', 'users.name')
            ->orderByDesc('bookings')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'partner_id' => (int) $row->partner_id,
            'name' => $row->company_name ?? ($row->contact_name ?? "Partner #{$row->partner_id}"),
            'bookings' => (int) $row->bookings,
            'revenue_cents' => (int) $row->revenue,
        ])->all();
    }

    /**
     * @return list<array<string, int|string>>
     */
    public function topDestinations(int $limit = 5): array
    {
        $rows = Booking::query()
            ->selectRaw('tours.location, tours.location_slug, COUNT(*) as bookings, '.self::REVENUE_CASE_SQL.' as revenue')
            ->join('tours', 'tours.id', '=', 'bookings.tour_id')
            ->groupBy('tours.location', 'tours.location_slug')
            ->orderByDesc('bookings')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row): array => [
            'location' => (string) ($row->location ?? $row->location_slug ?? 'Unknown'),
            'location_slug' => (string) ($row->location_slug ?? ''),
            'bookings' => (int) $row->bookings,
            'revenue_cents' => (int) $row->revenue,
        ])->all();
    }

    /**
     * @return array<string, int|float>
     */
    private function computeKpis(Carbon $start, Carbon $end, string $preset): array
    {
        $rangeBookings = Booking::query()
            ->whereBetween('created_at', [$start->startOfDay(), $end->copy()->endOfDay()]);

        $gross = (clone $rangeBookings)->whereIn('status', self::REVENUE_STATUSES)->sum('total_price');
        $commission = app(ApplicationFeeCalculator::class)->calculate(
            (int) $gross,
            (string) config('services.stripe.platform_commission_percent', '15.00'),
        );

        return [
            'total_bookings' => (clone $rangeBookings)->count(),
            'confirmed_bookings' => (clone $rangeBookings)->where('status', Booking::STATUS_CONFIRMED)->count(),
            'cancelled_bookings' => (clone $rangeBookings)->where('status', Booking::STATUS_CANCELLED)->count(),
            'gross_booking_value_cents' => (int) $gross,
            'platform_commission_cents' => $commission,
            'active_partners' => Partner::query()->where('is_active', true)->count(),
            'pending_partners' => Partner::query()->where('onboarding_status', 'pending')->count(),
            'published_tours' => Tour::query()->published()->count(),
            'pending_tours' => Tour::query()->where('status', 'pending_review')->count(),
            'total_travelers' => User::query()->where('role', 'traveler')->count(),
            'flagged_reviews' => Review::query()->where('status', 'flagged')->count(),
            'average_rating' => round((float) (Review::query()->whereIn('status', ['visible', 'flagged'])->avg('rating') ?? 0.0), 2),
            'preset' => $preset,
        ];
    }
}
