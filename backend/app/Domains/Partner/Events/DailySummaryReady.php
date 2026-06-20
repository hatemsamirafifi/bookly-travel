<?php

namespace App\Domains\Partner\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DailySummaryReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $partnerId,
        public array $summaryData,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('partner.' . $this->partnerId),
        ];
    }

    public function broadcastWith(): array
    {
        return array_merge([
            'type' => 'daily_summary_ready',
        ], $this->summaryData);
    }

    public function broadcastAs(): string
    {
        return 'DailySummaryReady';
    }
}