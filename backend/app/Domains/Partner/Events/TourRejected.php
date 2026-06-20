<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public int $tourId,
        public string $tourTitle,
        public ?string $reason,
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
            'type' => 'tour_rejected',
            'tour_id' => $this->tourId,
            'tour_title' => $this->tourTitle,
            'reason' => $this->reason,
        ];
    }

    public function broadcastAs(): string
    {
        return 'TourRejected';
    }
}
