<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Models\Booking;

class VoucherService
{
    /**
     * Generate a PDF voucher for a booking and return the file path.
     *
     * Uses DomPDF (barryvdh/laravel-dompdf) for PDF rendering.
     * The QR code is generated as an inline SVG within the voucher view.
     */
    public function generate(Booking $booking): string
    {
        $booking->loadMissing(['tour', 'tour.translations', 'traveler']);

        $locale = $booking->locale ?? 'en';
        $tourTitle = $booking->tour->translations->firstWhere('locale', $locale)?->title
            ?? $booking->tour->translations->firstWhere('locale', 'en')?->title
            ?? $booking->tour->slug;

        $qrData = json_encode([
            'ref' => $booking->reference,
            'date' => $booking->tour_date->toDateString(),
            'pax' => $booking->participant_count,
        ]);

        $data = [
            'booking' => $booking,
            'tourTitle' => $tourTitle,
            'tourDate' => $booking->tour_date->format('l, F j, Y'),
            'formattedTotal' => Booking::formatPrice($booking->total_price, $booking->currency ?? 'EUR'),
            'meetingPoint' => $booking->tour->meeting_point ?? '—',
            'qrData' => $qrData,
            'locale' => $locale,
        ];

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('voucher.booking', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = "vouchers/voucher-{$booking->reference}.pdf";
        $path = storage_path("app/{$filename}");

        // Ensure directory exists
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $pdf->output());

        return $path;
    }

    /**
     * Get the path to an existing voucher, or regenerate it.
     */
    public function getOrGenerate(Booking $booking): string
    {
        $path = storage_path("app/vouchers/voucher-{$booking->reference}.pdf");

        if (file_exists($path)) {
            return $path;
        }

        return $this->generate($booking);
    }
}
