<?php

namespace App\Domains\Booking\Controllers\Public;

use App\Domains\Booking\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService $voucherService,
    ) {}

    /**
     * Download a PDF voucher for the booking owner (FR-007, FR-008, FR-009).
     *
     * The controller only resolves the request + returns the response; booking
     * lookup, ownership scoping, and download-eligibility (post-payment,
     * non-cancelled: `confirmed` or `completed`) live in VoucherService, which
     * 404s for non-owners / cancelled / other statuses. The route's
     * `auth:sanctum` middleware blocks unauthenticated visitors and guests.
     *
     * GET /api/public/traveler/bookings/{reference}/voucher
     */
    public function download(Request $request, string $reference)
    {
        $path = $this->voucherService->downloadPathForOwner($reference, $request->user()->id);

        return response()->download($path, "voucher-{$reference}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
