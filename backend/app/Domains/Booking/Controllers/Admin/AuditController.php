<?php

namespace App\Domains\Booking\Controllers\Admin;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingAuditLog;
use App\Http\Requests\Admin\AuditIndexRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuditController
{
    public function index(AuditIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $query = BookingAuditLog::with('booking')
            ->orderBy('created_at', 'desc');

        if (isset($validated['booking_reference'])) {
            $booking = Booking::where('reference', $validated['booking_reference'])->first();
            if ($booking) {
                $query->where('booking_id', $booking->id);
            } else {
                $query->whereRaw('1 = 0'); // no results
            }
        }

        if (isset($validated['actor_type'])) {
            $query->where('actor_type', $validated['actor_type']);
        }

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (isset($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to']);
        }

        $paginator = $query->paginate(50, ['*'], 'page', (int) ($validated['page'] ?? 1));

        $data = $paginator->map(function (BookingAuditLog $log) {
            return [
                'id' => $log->id,
                'booking_reference' => $log->booking->reference ?? null,
                'actor_type' => $log->actor_type,
                'actor_id' => $log->actor_id,
                'action' => $log->action,
                'before_state' => $log->before_state,
                'after_state' => $log->after_state,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(string $reference): JsonResponse
    {
        $booking = Booking::with('auditLogs')->where('reference', $reference)->first();

        if (! $booking) {
            throw new NotFoundHttpException('Booking not found.');
        }

        $entries = $booking->auditLogs->sortBy('created_at')->map(function (BookingAuditLog $log) {
            return [
                'id' => $log->id,
                'actor_type' => $log->actor_type,
                'actor_id' => $log->actor_id,
                'action' => $log->action,
                'before_state' => $log->before_state,
                'after_state' => $log->after_state,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at->toIso8601String(),
            ];
        })->values()->all();

        // Linked financial events — placeholder until spec 008 payments table exists
        $linkedFinancialEvents = [];
        if (\Schema::hasTable('payments')) {
            $linkedFinancialEvents = \DB::table('payments')
                ->where('booking_id', $booking->id)
                ->orderBy('created_at')
                ->get()
                ->map(function ($payment) {
                    return [
                        'payment_id' => $payment->id,
                        'type' => $payment->type ?? 'charge',
                        'amount' => [
                            'amount' => $payment->amount ?? 0,
                            'currency' => $payment->currency ?? 'EUR',
                            'formatted' => Booking::formatPrice($payment->amount ?? 0, $payment->currency ?? 'EUR'),
                        ],
                        'status' => $payment->status ?? 'unknown',
                        'created_at' => $payment->created_at ? (new \DateTime($payment->created_at))->format('c') : null,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'data' => [
                'booking_reference' => $booking->reference,
                'entries' => $entries,
                'linked_financial_events' => $linkedFinancialEvents,
            ],
        ]);
    }
}
