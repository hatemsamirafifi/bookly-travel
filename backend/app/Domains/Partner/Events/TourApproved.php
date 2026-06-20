<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TourApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public int $tourId,
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
            'type' => 'tour_approved',
            'tour_id' => $this->tourId,
            'tour_title' => $this->tourTitle,
        ];
    }

    public function broadcastAs(): string
    {
        return 'TourApproved';
    }
}