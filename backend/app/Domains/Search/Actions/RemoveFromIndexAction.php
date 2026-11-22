<?php

namespace App\Domains\Search\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RemoveFromIndexAction implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $tourId
    ) {}

    public function handle(): void
    {
        // Reconcile current state under the shared Tour lock rather than
        // blindly removing, guarding against out-of-order A-B-A executions.
        (new IndexTourAction($this->tourId))->handle();
    }
}
