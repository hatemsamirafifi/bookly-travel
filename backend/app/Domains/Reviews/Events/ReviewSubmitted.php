<?php

namespace App\Domains\Reviews\Events;

use App\Domains\Reviews\Models\Review;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Review $review) {}
}
