<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public int $reviewId,
        public string $tourTitle,
        public int $rating,
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
            'type' => 'review_received',
            'review_id' => $this->reviewId,
            'tour_title' => $this->tourTitle,
            'rating' => $this->rating,
        ];
    }

    public function broadcastAs(): string
    {
        return 'ReviewReceived';
    }
}
