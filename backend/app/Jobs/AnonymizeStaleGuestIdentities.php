<?php

namespace App\Jobs;

use App\Models\GuestIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnonymizeStaleGuestIdentities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $threshold = now()->subMonths(24);

        // Find guest identities whose most recent booking is older than 24 months
        // and that have no future bookings
        $staleGuestIds = DB::table('bookings')
            ->select('guest_identity_id')
            ->whereNotNull('guest_identity_id')
            ->groupBy('guest_identity_id')
            ->havingRaw('MAX(tour_date) < ?', [$threshold])
            ->pluck('guest_identity_id')
            ->toArray();

        // Exclude any guests that have future bookings
        $futureGuestIds = DB::table('bookings')
            ->whereNotNull('guest_identity_id')
            ->where('tour_date', '>=', now())
            ->pluck('guest_identity_id')
            ->toArray();

        $staleGuestIds = array_diff($staleGuestIds, $futureGuestIds);

        if (empty($staleGuestIds)) {
            return;
        }

        $guests = GuestIdentity::whereNull('anonymized_at')
            ->whereNull('converted_user_id')
            ->whereIn('id', $staleGuestIds)
            ->get();

        foreach ($guests as $guest) {
            $guest->anonymize();
        }

        Log::info('Anonymized stale guest identities', [
            'count' => $guests->count(),
            'threshold_months' => 24,
        ]);
    }
}
