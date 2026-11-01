<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Admin\Services\FinancialOverviewService;
use App\Models\User;
use Filament\Pages\Page;

/**
 * Financial overview page (Admin 2.0, T-C02).
 *
 * Summarizes gross booking value, captured payments, refunds, platform
 * commission and partner payout liability from FinancialOverviewService,
 * then surfaces the reconciliation discrepancies beneath the cards.
 * Gated by `view_finances` OR `view_all_analytics`.
 */
class FinancialOverviewPage extends Page
{
    protected static string $view = 'filament.pages.financial-overview-page';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Financial Overview';

    protected static ?string $slug = 'financial-overview-page';

    protected static ?int $navigationSort = 3;

    /** @var array<string, int|string> */
    public array $summary = [];

    /** @var array{total_checked: int, matched_count: int, discrepancies: list<array{booking_reference: string, issue: string, booking_total_cents: int, payment_total_cents: int, ledger_net_cents: int}>} */
    public array $report = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'view_finances')
            || $authz->can($user, 'view_all_analytics');
    }

    public function mount(): void
    {
        $this->loadData();
    }

    /**
     * @return array<string, string> enum => readable label for the reconciliation issues.
     */
    public function issueLabels(): array
    {
        return [
            'no_succeeded_capture' => 'No succeeded capture payment',
            'payment_total_mismatch' => 'Payment total does not match booking',
            'ledger_net_mismatch' => 'Ledger net does not match payments',
            'uncaptured_net' => 'Cancelled booking retains uncaptured net',
        ];
    }

    public function issueLabel(string $issue): string
    {
        return $this->issueLabels()[$issue] ?? $issue;
    }

    public function loadData(): void
    {
        $service = app(FinancialOverviewService::class);

        $this->summary = $service->summary();
        $this->report = $service->reconciliationReport();
    }
}
