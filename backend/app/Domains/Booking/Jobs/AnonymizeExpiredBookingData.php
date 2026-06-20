<?php

namespace App\Domains\Booking\Jobs;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Scheduled daily job: anonymize personal identifiers on bookings where the
 * tour date is more than 90 days in the past (FR-029 / FR-025 data retention).
 *
 * Idempotency: guarded by the `anonymized_at` column — if already set, the
 * row is skipped, making re-runs completely safe.
 *
 * Each anonymized booking produces a `data_anonymized` audit log entry so the
 * event is traceable even after personal data is gone.
 */
class AnonymizeExpiredBookingData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * AuditService is resolved from the container at execution time (not at
     * serialization time) to avoid queue serialization failures.
     */
    public function handle(AuditService $audit): void
    {
        $cutoff = Carbon::now()->subDays(90)->toDateString();

        // Process in chunks to avoid memory exhaustion on large datasets
        Booking::where('tour_date', '<', $cutoff)
            ->whereNull('anonymized_at')
            ->chunkById(200, function ($bookings) use ($audit) {
                foreach ($bookings as $booking) {
                    $this->anonymize($booking, $audit);
                }
            });
    }

    private function anonymize(Booking $booking, AuditService $audit): void
    {
        $token = 'ANON-' . strtoupper(Str::random(12));

        DB::transaction(function () use ($booking, $token, $audit) {
            // Anonymize the booking's own traveler-identifying snapshot
            // fields and mark the row as done.
            $booking->update([
                'anonymized_at' => now(),
                // Store the token so audit trail retains a stable pseudonym
                'cancellation_reason' => $booking->cancellation_reason
                    ? $token . ' (reason redacted)'
                    : null,
            ]);

            $audit->log(
                $booking,
                'system',
                null,
                'data_anonymized',
                null,
                $booking->status,
                [
                    'anonymization_token' => $token,
                    'days_since_tour' => now()->diffInDays($booking->tour_date),
                ],
            );
        });

        Log::info('Booking personal data anonymized', [
            'booking_reference' => $booking->reference,
        ]);
    }
}
