<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public int $bookingId,
        public string $reference,
        public string $status,
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
            'type' => 'payment_status_changed',
            'booking_id' => $this->bookingId,
            'reference' => $this->reference,
            'status' => $this->status,
        ];
    }

    public function broadcastAs(): string
    {
        return 'PaymentStatusChanged';
    }
}
