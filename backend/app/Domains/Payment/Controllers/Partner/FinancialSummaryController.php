<?php

namespace App\Domains\Payment\Controllers\Partner;

use App\Domains\Payment\Models\Payment;
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

        $payments = Payment::query()
            ->whereHas('booking.tour', function ($q) use ($request, $validated) {
                $q->where('partner_id', (int) $request->user()->id);
                if (! empty($validated['tour_slug'])) {
                    $q->where('slug', $validated['tour_slug']);
                }
            })
            ->whereHas('booking', function ($q) use ($validated) {
                if (! empty($validated['date_from'])) {
                    $q->whereDate('created_at', '>=', $validated['date_from']);
                }
                if (! empty($validated['date_to'])) {
                    $q->whereDate('created_at', '<=', $validated['date_to']);
                }
            })
            ->get();

        $charges = $payments->where('type', 'charge');
        $refunds = $payments->where('type', 'refund');

        $totalRevenue = $charges->sum('amount');
        $totalRefunds = $refunds->sum('amount');
        $netRevenue = $totalRevenue - $totalRefunds;
        $bookingCount = $charges->count();
        $refundCount = $refunds->count();

        $currency = $payments->first()?->currency ?? 'EUR';

        $format = fn (int $amount) => number_format($amount / 100, 2) . ' ' . strtoupper($currency);

        return response()->json([
            'data' => [
                'total_revenue' => ['amount' => $totalRevenue, 'currency' => $currency, 'formatted' => $format($totalRevenue)],
                'total_refunds' => ['amount' => $totalRefunds, 'currency' => $currency, 'formatted' => $format($totalRefunds)],
                'net_revenue' => ['amount' => $netRevenue, 'currency' => $currency, 'formatted' => $format($netRevenue)],
                'booking_count' => $bookingCount,
                'refund_count' => $refundCount,
                'average_booking_value' => $bookingCount > 0
                    ? ['amount' => (int) round($totalRevenue / $bookingCount), 'currency' => $currency, 'formatted' => $format((int) round($totalRevenue / $bookingCount))]
                    : ['amount' => 0, 'currency' => $currency, 'formatted' => $format(0)],
            ],
            'meta' => [
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'tour_slug' => $validated['tour_slug'] ?? null,
            ],
        ]);
    }
}
