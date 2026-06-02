<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewBooking implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public int $bookingId,
        public string $reference,
        public string $tourTitle,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('partner.' . $this->partnerId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'new_booking',
            'booking_id' => $this->bookingId,
            'reference' => $this->reference,
            'tour_title' => $this->tourTitle,
        ];
    }

    public function broadcastAs(): string
    {
        return 'NewBooking';
    }
}