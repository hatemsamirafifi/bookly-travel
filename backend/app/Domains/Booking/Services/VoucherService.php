<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Spec 014: voucher PDF generation + freshness.
 *
 * The QR encodes the public verification URL {public_base_url}/v/{reference}
 * (FR-002, SC-009), rendered as a PNG data URI via GD (dompdf silently drops
 * inline `<svg>`, and PNG-via-imagick is unavailable; GD is always present).
 *
 * Freshness (FR-018, SC-008, R3): a content hash over voucher-relevant fields
 * is stored on the booking. `getOrGenerate` regenerates only when the file is
 * missing, the stored hash is null, or the stored hash differs from the current
 * content hash — so a changed date/participant count yields a fresh PDF while
 * unchanged (incl. status-only confirmed→completed) bookings reuse the cache.
 */
class VoucherService
{
    public function generate(Booking $booking): string
    {
        $data = $this->viewData($booking);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('voucher.booking', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "vouchers/voucher-{$booking->reference}.pdf";
        $path = storage_path("app/{$filename}");

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Atomic write (FR-002, Issue 3): write to a unique temp file in the
        // same directory, then rename() into place. Concurrent regenerate
        // calls (traveler + queue worker, two tabs) each write their own temp
        // file and the final rename is atomic — a reader never sees a
        // half-written PDF. Same filesystem is guaranteed by tempnam($dir).
        $temp = tempnam($dir, 'voucher-');
        if ($temp === false) {
            throw new \RuntimeException('Unable to create temporary file for voucher PDF.');
        }

        try {
            if (file_put_contents($temp, $pdf->output()) === false) {
                throw new \RuntimeException('Unable to write voucher PDF.');
            }

            if (! @rename($temp, $path)) {
                throw new \RuntimeException('Unable to finalize voucher PDF.');
            }
        } catch (\Throwable $e) {
            @unlink($temp);
            throw $e;
        }

        // Record freshness so future getOrGenerate calls can detect staleness.
        $booking->forceFill([
            'voucher_generated_at' => now(),
            'voucher_content_hash' => $data['contentHash'],
        ])->save();

        return $path;
    }

    /**
     * Serve the cached voucher, or regenerate when stale.
     */
    public function getOrGenerate(Booking $booking): string
    {
        $path = storage_path("app/vouchers/voucher-{$booking->reference}.pdf");
        $currentHash = $this->contentHash($booking);

        if (file_exists($path)
            && $booking->voucher_generated_at !== null
            && $booking->voucher_content_hash === $currentHash) {
            return $path;
        }

        return $this->generate($booking);
    }

    /**
     * Resolve the voucher's owner-scoped, download-eligible booking and return
     * the on-disk PDF path (FR-007, FR-008, R10). The eligibility rule — any
     * post-payment, non-cancelled booking (`confirmed` or `completed`) — lives
     * in the service, not the controller. Ownership scoping + `firstOrFail`
     * means non-owners, cancelled, and other statuses all 404 (no enumeration
     * signal); the route's `auth:sanctum` middleware blocks unauthenticated
     * visitors and guests (FR-009).
     */
    public function downloadPathForOwner(string $reference, int $travelerId): string
    {
        $booking = Booking::where('reference', $reference)
            ->where('traveler_id', $travelerId)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->firstOrFail();

        return $this->getOrGenerate($booking);
    }

    /**
     * Resolve the tour title in the booking's locale with EN fallback, then the
     * tour slug. Single source of truth for the locale-fallback chain used by
     * the voucher PDF, the freshness hash, and the public verification surface
     * (FR-014, FR-015, FR-022, FR-028 — no duplication of booking-read logic).
     */
    public function resolveTourTitle(Booking $booking): string
    {
        $booking->loadMissing(['tour', 'tour.translations']);
        $tour = $booking->tour;
        if (! $tour) {
            return '';
        }

        $locale = $booking->locale ?? 'en';

        return $tour->translations->firstWhere('locale', $locale)?->title
            ?? $tour->translations->firstWhere('locale', 'en')?->title
            ?? $tour->slug;
    }

    /**
     * SHA-256 over the voucher-relevant fields. Status is intentionally excluded
     * so confirmed→completed does NOT trigger regeneration (SC-008).
     */
    public function contentHash(Booking $booking): string
    {
        $tourTitle = $this->resolveTourTitle($booking);
        $tour = $booking->tour;
        $locale = $booking->locale ?? 'en';

        return hash('sha256', json_encode([
            'reference' => $booking->reference,
            'tour_title' => $tourTitle,
            'tour_date' => $booking->tour_date?->toDateString(),
            'participant_count' => (int) $booking->participant_count,
            'total_price' => (int) $booking->total_price,
            'currency' => $booking->currency ?? 'EUR',
            'meeting_point' => $tour?->meeting_point ?? '',
            'locale' => $locale,
        ]));
    }

    /**
     * The public URL the voucher QR encodes (FR-002, SC-009, R2).
     */
    public function verificationUrl(string $reference): string
    {
        return rtrim((string) config('services.voucher.public_base_url', 'https://bookly.travel'), '/')
            . '/v/' . $reference;
    }

    /**
     * Build the view payload, including the inline-SVG QR.
     */
    private function viewData(Booking $booking): array
    {
        $booking->loadMissing(['tour', 'tour.translations', 'traveler']);
        $locale = $booking->locale ?? 'en';
        $tourTitle = $this->resolveTourTitle($booking);

        $qrUrl = $this->verificationUrl($booking->reference);
        $qrDataUri = $this->renderQrPngDataUri($qrUrl);

        return [
            'booking' => $booking,
            'tourTitle' => $tourTitle,
            'tourDate' => $booking->tour_date->format('l, F j, Y'),
            'formattedTotal' => Booking::formatPrice($booking->total_price, $booking->currency ?? 'EUR'),
            'meetingPoint' => $booking->tour->meeting_point ?? '—',
            'qrUrl' => $qrUrl,
            'qrDataUri' => $qrDataUri,
            'locale' => $locale,
            'contentHash' => $this->contentHash($booking),
        ];
    }

    /**
     * Render the QR as a base64 PNG data URI that DomPDF embeds via `<img>`.
     * Uses GD (always available) + bacon/qr-code directly — dompdf silently
     * drops inline `<svg>`, and simple-qrcode's PNG format requires imagick.
     */
    private function renderQrPngDataUri(string $url): string
    {
        $qr = Encoder::encode(
            $url,
            ErrorCorrectionLevel::forBits(1), // Level L (low) — sufficient for a printed voucher
            Encoder::DEFAULT_BYTE_MODE_ECODING
        );
        $matrix = $qr->getMatrix();
        $modules = $matrix->getWidth();
        $quiet = 4; // standard quiet zone (4 modules)
        $cell = 10; // px per module — renders crisp when CSS scales to 120px

        $size = ($modules + 2 * $quiet) * $cell;
        $image = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $size, $size, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $quiet) * $cell;
                    $py = ($y + $quiet) * $cell;
                    imagefilledrectangle($image, $px, $py, $px + $cell - 1, $py + $cell - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
