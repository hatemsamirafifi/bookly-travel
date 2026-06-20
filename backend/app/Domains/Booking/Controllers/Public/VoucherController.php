<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService $voucherService,
    ) {}

    /**
     * Download a PDF voucher for a confirmed booking.
     *
     * GET /api/public/traveler/bookings/{reference}/voucher
     */
    public function download(Request $request, string $reference)
    {
        $booking = Booking::where('reference', $reference)
            ->where('traveler_id', $request->user()->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->firstOrFail();

        $path = $this->voucherService->getOrGenerate($booking);

        return response()->download($path, "voucher-{$booking->reference}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
