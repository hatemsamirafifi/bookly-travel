<?php

namespace App\Filament\Widgets;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Models\User;
use Filament\Widgets\Widget;

/**
 * Dashboard queue-shortcut tiles (Spec 013, US7, FR-013).
 *
 * Renders one tile per moderation queue with the live pending count and a
 * deep-link into the corresponding filtered Filament resource list. Only
 * visible to admins holding the `view_all_analytics` flag.
 */
class QueueShortcutsWidget extends Widget
{
    protected static string $view = 'filament.widgets.queue-shortcuts-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && app(AdminAuthorizationService::class)->can($user, 'view_all_analytics');
    }

    /** @return array<string, array{label: string, count: int, url: string, color: string}> */
    public function queues(): array
    {
        $counts = PlatformOverviewWidget::queueCounts();

        return [
            'partners' => [
                'label' => 'Pending Partners',
                'count' => $counts['partners'],
                'url' => '/admin/partners?tableFilters[onboarding_status][value]=pending',
                'color' => 'warning',
            ],
            'tours' => [
                'label' => 'Pending Tours',
                'count' => $counts['tours'],
                'url' => '/admin/tours?tableFilters[status][value]=pending_review',
                'color' => 'warning',
            ],
            'reviews' => [
                'label' => 'Flagged Reviews',
                'count' => $counts['reviews'],
                'url' => '/admin/reviews?tableFilters[status][value]=flagged',
                'color' => 'danger',
            ],
            'bookings' => [
                'label' => 'All Bookings',
                'count' => $counts['bookings'],
                'url' => '/admin/bookings',
                'color' => 'primary',
            ],
        ];
    }
}