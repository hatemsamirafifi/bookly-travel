<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PlatformOverviewWidget;
use App\Filament\Widgets\QueueShortcutsWidget;
use App\Filament\Widgets\RecentBookingsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Custom admin landing dashboard (Spec 013, US7, FR-013).
 *
 * Hosts the platform-overview stat cards, the queue-shortcut tiles, and the
 * recent-bookings table. Queue shortcuts + recent bookings are gated by the
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
        ];
    }
}
