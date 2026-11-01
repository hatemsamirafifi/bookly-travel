<?php

namespace App\Domains\Admin\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\FinancialLedgerEntry;
use App\Domains\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Financial overview + reconciliation + ledger export (Admin 2.0, T-C01).
 *
 * All monetary math stays in integer cents. The commission figure comes from
 * `financial_ledger_entries` (`entry_type = 'commission'`); while no commission
 * entries have been recorded yet it falls back to 15% of confirmed booking
 * value so the partner payout liability stays meaningful. The ledger export
 * requires `export_financial_reports` OR `view_all_analytics` and writes an
 * immutable `finance.export` governance audit entry.
 */
class FinancialOverviewService
{
    public const DEFAULT_CURRENCY = 'EUR';

    public const FALLBACK_COMMISSION_BPS = 1500;

    private const CASHABLE_BOOKING_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_COMPLETED,
    ];

    private const RECONCILIATION_BOOKING_STATUSES = [
        Booking::STATUS_CONFIRMED,
        Booking::STATUS_COMPLETED,
        Booking::STATUS_CANCELLED,
    ];

    public const LEDGER_COLUMNS = [
        'id',
        'booking_reference',
        'payment_id',
        'entry_type',
        'amount',
        'currency',
        'actor',
        'description',
        'created_at',
    ];

    public function __construct(
        private readonly GovernanceAuditService $audit,
        private readonly AdminAuthorizationService $authz,
    ) {}

    public function canView(User $user): bool
    {
        return $this->authz->can($user, 'view_finances')
            || $this->authz->can($user, 'view_all_analytics');
    }

    /**
     * Integer-cent financial totals (plus formatted EUR strings) for the
     * Financial Overview page. `dateFrom`/`dateTo` are inclusive
     * `created_at` bounds applied to bookings, payments and ledger entries.
     *
     * @return array<string, int|string>
     */
    public function summary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $grossBookingValueCents = (int) Booking::query()
            ->whereIn('status', self::CASHABLE_BOOKING_STATUSES)
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->sum('total_price');

        $capturedPaymentsCents = (int) Payment::query()
            ->where('type', 'capture')
            ->where('status', 'succeeded')
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->sum('amount');

        $refundedAmountCents = (int) Payment::query()
            ->where('type', 'refund')
            ->where('status', 'succeeded')
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->sum('amount');

        $commissionFromLedgerCents = (int) FinancialLedgerEntry::query()
            ->where('entry_type', 'commission')
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->sum('amount');

        $platformCommissionCents = $commissionFromLedgerCents > 0
            ? $commissionFromLedgerCents
            : (int) round($grossBookingValueCents * self::FALLBACK_COMMISSION_BPS / 10000);

        $partnerPayoutLiabilityCents = max(
            0,
            $capturedPaymentsCents - $refundedAmountCents - $platformCommissionCents,
        );

        $failedPaymentsCount = (int) Payment::query()
            ->where('status', 'failed')
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->count();

        return [
            'currency' => self::DEFAULT_CURRENCY,
            'gross_booking_value_cents' => $grossBookingValueCents,
            'gross_booking_value' => Booking::formatPrice($grossBookingValueCents, self::DEFAULT_CURRENCY),
            'captured_payments_cents' => $capturedPaymentsCents,
            'captured_payments' => Booking::formatPrice($capturedPaymentsCents, self::DEFAULT_CURRENCY),
            'refunded_amount_cents' => $refundedAmountCents,
            'refunded_amount' => Booking::formatPrice($refundedAmountCents, self::DEFAULT_CURRENCY),
            'platform_commission_cents' => $platformCommissionCents,
            'platform_commission' => Booking::formatPrice($platformCommissionCents, self::DEFAULT_CURRENCY),
            'partner_payout_liability_cents' => $partnerPayoutLiabilityCents,
            'partner_payout_liability' => Booking::formatPrice($partnerPayoutLiabilityCents, self::DEFAULT_CURRENCY),
            'failed_payments_count' => $failedPaymentsCount,
        ];
    }

    /**
     * Reconcile bookings (confirmed/completed/cancelled) against payment and
     * ledger state. Data comes from three sources:
     * `bookings` (booking_total_cents), `payments` (succeeded capture/refund
     * sums) and `financial_ledger_entries` (net = debits − credits).
     *
     * @return array{
     *     total_checked: int,
     *     matched_count: int,
     *     discrepancies: list<array{
     *         booking_reference: string,
     *         issue: string,
     *         booking_total_cents: int,
     *         payment_total_cents: int,
     *         ledger_net_cents: int,
     *     }>
     * }
     */
    public function reconciliationReport(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $paymentsByBooking = Payment::query()
            ->selectRaw("booking_id, SUM(CASE WHEN type = 'capture' AND status = 'succeeded' THEN amount ELSE 0 END) as captured_total, SUM(CASE WHEN type = 'refund' AND status = 'succeeded' THEN amount ELSE 0 END) as refunded_total")
            ->groupBy('booking_id')
            ->get()
            ->keyBy('booking_id');

        $ledgerByBooking = FinancialLedgerEntry::query()
            ->selectRaw("booking_id, SUM(CASE WHEN entry_type = 'debit' THEN amount WHEN entry_type = 'credit' THEN -amount ELSE 0 END) as ledger_net")
            ->groupBy('booking_id')
            ->get()
            ->keyBy('booking_id');

        $totalChecked = 0;
        $matchedCount = 0;
        $discrepancies = [];

        Booking::query()
            ->whereIn('status', self::RECONCILIATION_BOOKING_STATUSES)
            ->where(fn (Builder $query) => $this->applyDateFilter($query, $dateFrom, $dateTo))
            ->get()
            ->each(function (Booking $booking) use ($paymentsByBooking, $ledgerByBooking, &$totalChecked, &$matchedCount, &$discrepancies): void {
                $totalChecked++;

                $paymentTotal = (int) ($paymentsByBooking[$booking->id]->captured_total ?? 0);
                $refundedTotal = (int) ($paymentsByBooking[$booking->id]->refunded_total ?? 0);
                $ledgerNet = (int) ($ledgerByBooking[$booking->id]->ledger_net ?? 0);

                $expectedPaymentTotal = $booking->status === Booking::STATUS_CANCELLED
                    ? $refundedTotal
                    : (int) $booking->total_price;

                $issue = null;

                if ($booking->status !== Booking::STATUS_CANCELLED && $paymentTotal === 0) {
                    $issue = 'no_succeeded_capture';
                } elseif ($paymentTotal !== $expectedPaymentTotal) {
                    $issue = 'payment_total_mismatch';
                } elseif ($ledgerNet !== $paymentTotal - $refundedTotal) {
                    $issue = 'ledger_net_mismatch';
                }

                if ($issue === null) {
                    $matchedCount++;

                    return;
                }

                $discrepancies[] = [
                    'booking_reference' => (string) $booking->reference,
                    'issue' => $issue,
                    'booking_total_cents' => (int) $booking->total_price,
                    'payment_total_cents' => $paymentTotal,
                    'ledger_net_cents' => $ledgerNet,
                ];
            });

        return [
            'total_checked' => $totalChecked,
            'matched_count' => $matchedCount,
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * CSV export of `financial_ledger_entries` (`finance.export`).
     *
     * @param  array<string, mixed>  $filters  entry_type, booking_id, date_from, date_to
     */
    public function exportLedgerCsv(User $actor, array $filters = []): string
    {
        if (! $this->canExport($actor)) {
            abort(403, 'You are not authorized to export financial ledger data.');
        }

        $rows = $this->ledgerQuery($filters)->get();

        $handle = fopen('php://temp', 'rb+');
        try {
            fputcsv($handle, self::LEDGER_COLUMNS);

            foreach ($rows as $entry) {
                fputcsv($handle, [
                    $entry->id,
                    $entry->booking?->reference ?? '',
                    $entry->payment_id,
                    $entry->entry_type,
                    $entry->amount,
                    $entry->currency,
                    $entry->actor,
                    $entry->description,
                    (string) $entry->created_at,
                ]);
            }

            rewind($handle);

            $this->audit->log(
                $actor,
                'finance.export',
                null,
                null,
                null,
                ['filters' => $filters, 'row_count' => $rows->count()],
                'financial_ledger_entry',
            );

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    public function downloadLedgerCsv(User $actor, array $filters = []): StreamedResponse
    {
        $csv = $this->exportLedgerCsv($actor, $filters);

        return response()->streamDownload(function () use ($csv): void {
            echo "\xEF\xBB\xBF";
            echo $csv;
        }, 'financial-ledger-export-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function canExport(User $user): bool
    {
        return $this->authz->can($user, 'export_financial_reports')
            || $this->authz->can($user, 'view_all_analytics');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function ledgerQuery(array $filters): Builder
    {
        $query = FinancialLedgerEntry::query()->with('booking:id,reference');

        if (! empty($filters['entry_type']) && is_string($filters['entry_type'])) {
            $query->where('entry_type', (string) Str::of($filters['entry_type'])->trim());
        }

        if (! empty($filters['booking_id']) && is_numeric($filters['booking_id'])) {
            $query->where('booking_id', (int) $filters['booking_id']);
        }

        return $query
            ->when(! empty($filters['date_from']), fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->orderBy('created_at')
            ->orderBy('id');
    }

    private function applyDateFilter(Builder $query, ?string $dateFrom, ?string $dateTo): void
    {
        $query->when(
            ! empty($dateFrom),
            fn (Builder $q) => $q->whereDate('created_at', '>=', $dateFrom),
        )->when(
            ! empty($dateTo),
            fn (Builder $q) => $q->whereDate('created_at', '<=', $dateTo),
        );
    }
}
