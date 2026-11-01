<?php

namespace App\Filament\Widgets;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\PlatformAnalyticsService;
use App\Models\Tour;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Top marketplace rankings (Admin 2.0, T-B01).
 *
 * Best-selling tour, partner, and destination ranked by confirmed booking
 * volume, from PlatformAnalyticsService. Gated by `view_all_analytics`.
 */
class TopRankingsWidget extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Top Rankings';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminAuthorizationService::class)->can($user, 'view_all_analytics');
    }

    protected function getStats(): array
    {
        $analytics = app(PlatformAnalyticsService::class);

        $topTour = $analytics->topTours(1)[0] ?? null;
        $topPartner = $analytics->topPartners(1)[0] ?? null;
        $topDestination = $analytics->topDestinations(1)[0] ?? null;

        $stats = [
            Stat::make(
                'Top Tour by Bookings',
                $topTour !== null ? $this->tourTitle($topTour) : 'No bookings yet',
            )
                ->description($topTour !== null
                    ? sprintf('%d bookings · %s revenue', $topTour['bookings'], Tour::formatPrice((int) $topTour['revenue_cents'], 'EUR'))
                    : 'Once bookings start, the best-selling tour appears here.')
                ->descriptionIcon('heroicon-m-map')
                ->color($topTour !== null ? 'success' : 'gray'),
            Stat::make(
                'Top Partner by Bookings',
                $topPartner !== null ? (string) $topPartner['name'] : 'No bookings yet',
            )
                ->description($topPartner !== null
                    ? sprintf('%d bookings · %s revenue', $topPartner['bookings'], Tour::formatPrice((int) $topPartner['revenue_cents'], 'EUR'))
                    : 'Once bookings start, the top partner appears here.')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color($topPartner !== null ? 'primary' : 'gray'),
            Stat::make(
                'Top Destination by Bookings',
                $topDestination !== null ? (string) $topDestination['location'] : 'No bookings yet',
            )
                ->description($topDestination !== null
                    ? sprintf('%d bookings · %s revenue', $topDestination['bookings'], Tour::formatPrice((int) $topDestination['revenue_cents'], 'EUR'))
                    : 'Once bookings start, the most-booked destination appears here.')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color($topDestination !== null ? 'warning' : 'gray'),
        ];

        return $stats;
    }

    private function tourTitle(array $topTour): string
    {
        $title = (string) ($topTour['title'] ?? '');

        return $title !== '' ? $title : (string) ($topTour['slug'] ?? '');
    }
}
