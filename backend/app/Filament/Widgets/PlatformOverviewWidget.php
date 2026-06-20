<?php

namespace App\Filament\Widgets;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Models\Partner;
use App\Models\Tour;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $todayBookings = Booking::whereDate('created_at', today())->count();
        $weekBookings = Booking::where('created_at', '>=', now()->subWeek())->count();
        $monthBookings = Booking::where('created_at', '>=', now()->subMonth())->count();

        $monthRevenue = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonth())
            ->sum('total_price');

        $pendingPartners = Partner::where('onboarding_status', 'pending')->count();
        $pendingTours = Tour::where('status', 'pending_review')->count();
        $activePartners = Partner::where('is_active', true)->count();
        $publishedTours = Tour::where('status', 'published')->count();

        return [
            Stat::make('Today\'s Bookings', $todayBookings)
                ->description("This week: {$weekBookings}")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart(self::getBookingTrend()),
            Stat::make('Monthly Revenue', Booking::formatPrice($monthRevenue, 'EUR'))
                ->description("{$monthBookings} bookings this month")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make('Pending Partners', $pendingPartners)
                ->description("{$activePartners} active partners")
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color($pendingPartners > 0 ? 'warning' : 'success'),
            Stat::make('Pending Tours', $pendingTours)
                ->description("{$publishedTours} published tours")
                ->descriptionIcon('heroicon-m-map')
                ->color($pendingTours > 0 ? 'warning' : 'success'),
        ];
    }

    /**
     * Generate a simple 7-day booking trend for the chart sparkline.
     */
    private static function getBookingTrend(): array
    {
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $trend[] = Booking::whereDate('created_at', now()->subDays($i))->count();
        }

        return $trend;
    }
}
