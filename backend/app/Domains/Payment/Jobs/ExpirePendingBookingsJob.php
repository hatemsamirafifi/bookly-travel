<?php

namespace App\Domains\Payment\Jobs;

use App\Domains\Booking\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingBookingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(\App\Domains\Booking\Services\AuditService $auditService): void
    {
        $expired = DB::transaction(function () use ($auditService) {
            $bookings = Booking::where('status', Booking::STATUS_PENDING_PAYMENT)
                ->where('pending_expires_at', '<=', now())
                ->lockForUpdate()
                ->get();

            $count = 0;
            foreach ($bookings as $booking) {
                $beforeState = $booking->status;
                $booking->update(['status' => Booking::STATUS_EXPIRED]);
                
                $auditService->log(
                    $booking,
                    'system',
                    null,
                    'booking.expired',
                    $beforeState,
                    Booking::STATUS_EXPIRED,
                    ['reason' => 'pending_payment_timeout']
                );

                $count++;
            }

            return $count;
        });

        if ($expired > 0) {
            Log::info('Expired pending payment bookings', ['count' => $expired]);
        }
    }
}
