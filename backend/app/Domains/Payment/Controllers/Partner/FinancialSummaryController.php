<?php

namespace App\Domains\Payment\Controllers\Partner;

use App\Domains\Booking\Models\Booking;
use App\Domains\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialSummaryController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour_slug' => 'sometimes|string',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
        ]);

        // Resolve the Partner model from the authenticated user's `partner`
        // relation. tours.partner_id references partners.id, NOT users.id —
        // the two are independent sequences and only coincide in a freshly
        // migrated DB, so scoping by $request->user()->id is wrong under any
        // prior activity (and fails isolation in the full test suite).
        $partner = $request->user()->partner;
        abort_unless($partner !== null, 403, 'You are not authorized to view financial summaries.');

        // F10: only settled money counts. Charges must be `succeeded` and
        // refunds `refunded` — pending/failed charges are excluded so the
        // summary reflects realized revenue, not authorizations. Aggregates
        // are computed per currency in the DB (no full-row hydration).
        $chargeRows = $this->aggregate($partner->id, $validated, 'charge', 'succeeded');
        $refundRows = $this->aggregate($partner->id, $validated, 'refund', 'refunded');

        $currencies = $chargeRows->keys()
            ->merge($refundRows->keys())
            ->unique()
            ->values();

        $perCurrency = $currencies->map(fn (string $currency) => $this->currencyTotals(
            $currency,
            (int) ($chargeRows[$currency]->total ?? 0),
            (int) ($chargeRows[$currency]->cnt ?? 0),
            (int) ($refundRows[$currency]->total ?? 0),
            (int) ($refundRows[$currency]->cnt ?? 0),
        ))->values();

        // Flat top-level totals use the single currency when there is exactly
        // one (backwards-compatible shape). For mixed currencies the
        // per-currency `totals` array is authoritative; the flat keys fall
        // back to the first currency and mixed_currency is flagged.
        $mixedCurrency = $currencies->count() > 1;
        $flatCurrency = $currencies->first() ?? 'EUR';
        $flatCharges = (int) ($chargeRows->sum('total') ?? 0);
        $flatRefunds = (int) ($refundRows->sum('total') ?? 0);
        $flatBookingCount = (int) ($chargeRows->sum('cnt') ?? 0);
        $flatRefundCount = (int) ($refundRows->sum('cnt') ?? 0);

        return response()->json([
            'data' => array_merge(
                $this->flatTotals($flatCurrency, $flatCharges, $flatRefunds, $flatBookingCount, $flatRefundCount),
                ['totals' => $perCurrency->all()],
            ),
            'meta' => [
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'tour_slug' => $validated['tour_slug'] ?? null,
                'mixed_currency' => $mixedCurrency,
            ],
        ]);
    }

    /**
     * Sum + count of payments of a given type/status, scoped to this
     * partner's tours (and optional tour/date filters), grouped by currency.
     */
    private function aggregate(int $partnerId, array $validated, string $type, string $status)
    {
        return Payment::query()
            ->whereHas('booking.tour', function (Builder $tour) use ($partnerId, $validated) {
                $tour->where('partner_id', $partnerId);
                if (! empty($validated['tour_slug'])) {
                    $tour->where('slug', $validated['tour_slug']);
                }
            })
            ->whereHas('booking', function (Builder $booking) use ($validated) {
                if (! empty($validated['date_from'])) {
                    $booking->whereDate('created_at', '>=', $validated['date_from']);
                }
                if (! empty($validated['date_to'])) {
                    $booking->whereDate('created_at', '<=', $validated['date_to']);
                }
            })
            ->where('type', $type)
            ->where('status', $status)
            ->selectRaw('currency, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS cnt')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');
    }

    private function currencyTotals(string $currency, int $charges, int $bookingCount, int $refunds, int $refundCount): array
    {
        $net = $charges - $refunds;

        return [
            'currency' => $currency,
            'total_revenue' => $this->money($charges, $currency),
            'total_refunds' => $this->money($refunds, $currency),
            'net_revenue' => $this->money($net, $currency),
            'booking_count' => $bookingCount,
            'refund_count' => $refundCount,
            'average_booking_value' => $bookingCount > 0
                ? $this->money((int) round($charges / $bookingCount), $currency)
                : $this->money(0, $currency),
        ];
    }

    private function flatTotals(string $currency, int $charges, int $refunds, int $bookingCount, int $refundCount): array
    {
        $net = $charges - $refunds;

        return [
            'total_revenue' => $this->money($charges, $currency),
            'total_refunds' => $this->money($refunds, $currency),
            'net_revenue' => $this->money($net, $currency),
            'booking_count' => $bookingCount,
            'refund_count' => $refundCount,
            'average_booking_value' => $bookingCount > 0
                ? $this->money((int) round($charges / $bookingCount), $currency)
                : $this->money(0, $currency),
        ];
    }

    private function money(int $amount, string $currency): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => Booking::formatPrice($amount, $currency),
        ];
    }
}