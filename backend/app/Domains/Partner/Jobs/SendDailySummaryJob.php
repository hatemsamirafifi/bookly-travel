<?php

namespace App\Domains\Partner\Jobs;

use App\Domains\Booking\Models\Booking;
use App\Domains\Partner\Events\DailySummaryReady;
use App\Domains\Partner\Models\Notification;
use App\Domains\Partner\Models\Partner;
use App\Domains\Reviews\Models\Review;
use App\Models\Tour;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendDailySummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $partnerId,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $partner = Partner::find($this->partnerId);

        if (! $partner) {
            return;
        }

        $today = now()->startOfDay();

        $newBookingsCount = Booking::whereHas('tour', fn ($query) => $query->where('partner_id', $this->partnerId))
            ->where('created_at', '>=', $today)
            ->count();

        $revenue = (int) Booking::whereHas('tour', fn ($query) => $query->where('partner_id', $this->partnerId))
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->where('payment_confirmed_at', '>=', $today)
            ->sum('total_price');

        $newReviewsCount = Review::whereHas('tour', fn ($query) => $query->where('partner_id', $this->partnerId))
            ->where('created_at', '>=', $today)
            ->count();

        $summaryData = [
            'new_bookings_count' => $newBookingsCount,
            'revenue' => $revenue,
            'new_reviews_count' => $newReviewsCount,
        ];

        Notification::create([
            'partner_id' => $this->partnerId,
            'type' => 'generic',
            'title' => 'Daily Summary Ready',
            'body' => sprintf(
                'You had %d new booking(s), %s in revenue, and %d new review(s) today.',
                $newBookingsCount,
                Tour::formatPrice($revenue, 'EUR'),
                $newReviewsCount,
            ),
            'data' => $summaryData,
        ]);

        broadcast(new DailySummaryReady(
            partnerId: $this->partnerId,
            summaryData: $summaryData,
        ));
    }
}