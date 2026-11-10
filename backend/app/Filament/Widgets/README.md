# Widgets

<!-- EGM contract -->

## Overview

Admin dashboard summaries for platform totals, booking trends and recent bookings, rankings, and links to moderation queues.

## Interface

- [BookingTrendsChartWidget.php](./BookingTrendsChartWidget.php#L17) declares `BookingTrendsChartWidget`, `BookingTrendsChartWidget::canView()`.
- [PlatformOverviewWidget.php](./PlatformOverviewWidget.php#L14) declares `PlatformOverviewWidget`, `PlatformOverviewWidget::canView()`, `PlatformOverviewWidget::queueCounts()`.
- [QueueShortcutsWidget.php](./QueueShortcutsWidget.php#L16) declares `QueueShortcutsWidget`, `QueueShortcutsWidget::canView()`, `QueueShortcutsWidget::queues()`.
- [RecentBookingsWidget.php](./RecentBookingsWidget.php#L17) declares `RecentBookingsWidget`, `RecentBookingsWidget::canView()`, `RecentBookingsWidget::table()`.
- [TopRankingsWidget.php](./TopRankingsWidget.php#L18) declares `TopRankingsWidget`, `TopRankingsWidget::canView()`.

## Invariants

- `PlatformOverviewWidget`, `BookingTrendsChartWidget`, `RecentBookingsWidget`, `TopRankingsWidget`, and `QueueShortcutsWidget` check `view_all_analytics` in `canView`. [PlatformOverviewWidget.php](./PlatformOverviewWidget.php#L20) [BookingTrendsChartWidget.php](./BookingTrendsChartWidget.php#L27) [RecentBookingsWidget.php](./RecentBookingsWidget.php#L25) [TopRankingsWidget.php](./TopRankingsWidget.php#L26) [QueueShortcutsWidget.php](./QueueShortcutsWidget.php#L24)

## Side Effects

None recorded in this contract.

## Verification

- From `backend/`, run `php artisan test --configuration=phpunit.pgsql.xml` with the PostgreSQL and Meilisearch test services and environment from the CI workflow.

<!-- End EGM contract -->
