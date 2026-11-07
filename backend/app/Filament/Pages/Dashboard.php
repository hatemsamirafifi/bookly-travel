<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BookingTrendsChartWidget;
use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\QueueShortcutsWidget;
use App\Filament\Widgets\RecentBookingsWidget;
use App\Filament\Widgets\TopRankingsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Custom admin landing dashboard (Spec 013, US7, FR-013; Admin 2.0 T-B01).
 *
 * Hosts the platform-overview stat cards, the queue-shortcut tiles, the
 * recent-bookings table, plus the T-B01 analytics widgets (booking/revenue
 * trend chart and top rankings). Analytics widgets are gated by the
 * `view_all_analytics` flag (each widget's canView()).
 */
class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            QueueShortcutsWidget::class,
            PlatformOverviewWidget::class,
            RecentBookingsWidget::class,
            BookingTrendsChartWidget::class,
            TopRankingsWidget::class,
        ];
    }
}
