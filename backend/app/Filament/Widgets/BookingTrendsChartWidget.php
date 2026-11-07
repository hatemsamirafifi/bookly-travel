<?php

namespace App\Filament\Widgets;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\PlatformAnalyticsService;
use App\Models\User;
use Filament\Widgets\ChartWidget;

/**
 * Bookings + revenue trend line chart (Admin 2.0, T-B01).
 *
 * Draws daily bookings and gross-revenue (EUR) series for the active
 * date-range preset (default 30d) from PlatformAnalyticsService. Gated by
 * `view_all_analytics` like the other analytics dashboard widgets.
 */
class BookingTrendsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Bookings & Revenue Trends';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public string $preset = '30d';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminAuthorizationService::class)->can($user, 'view_all_analytics');
    }

    protected function getFilters(): ?array
    {
        return [
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
        ];
    }

    protected function getData(): array
    {
        $trends = app(PlatformAnalyticsService::class)
            ->bookingAndRevenueTrends($this->preset);

        return [
            'datasets' => [
                [
                    'label' => 'Bookings',
                    'data' => $trends['bookings'],
                    'borderColor' => '#0A2540',
                    'backgroundColor' => 'rgba(10, 37, 64, 0.1)',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Revenue (EUR)',
                    'data' => array_map(
                        fn (int $cents): float => $cents / 100,
                        $trends['revenue_cents'],
                    ),
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $trends['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'id' => 'y',
                    'position' => 'left',
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'y1' => [
                    'id' => 'y1',
                    'position' => 'right',
                    'beginAtZero' => true,
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
