<?php

namespace App\Domains\Payment\Controllers\Admin;

use App\Domains\Payment\Models\FinancialLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialLedgerController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_reference' => 'sometimes|string',
            'entry_type' => 'sometimes|string|in:debit,credit',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = FinancialLedgerEntry::query()->with('booking');

        if (! empty($validated['booking_reference'])) {
            $query->whereHas('booking', function ($q) use ($validated) {
                $q->where('reference', $validated['booking_reference']);
            });
        }

        if (! empty($validated['entry_type'])) {
            $query->where('entry_type', $validated['entry_type']);
        }

        if (! empty($validated['date_from'])) {
            $query->where('created_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<=', $validated['date_to'] . ' 23:59:59');
        }

        $entries = $query->orderBy('created_at', 'desc')->paginate(50);

        $data = $entries->through(fn (FinancialLedgerEntry $entry) => [
            'id' => $entry->id,
            'booking_reference' => $entry->booking?->reference,
            'payment_id' => $entry->payment_id,
            'entry_type' => $entry->entry_type,
            'amount' => [
                'amount' => $entry->amount,
                'currency' => $entry->currency,
                'formatted' => number_format($entry->amount / 100, 2) . ' ' . strtoupper($entry->currency),
            ],
            'actor' => $entry->actor,
            'description' => $entry->description,
            'created_at' => $entry->created_at->toIso8601String(),
        ]);

        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }
}
