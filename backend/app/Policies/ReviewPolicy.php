<?php

namespace App\Policies;

use App\Domains\Reviews\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Determine if the user can moderate (hide/reinstate) reviews.
     * Only admin users are authorized.
     */
    public function manage(User $user, Review $review): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine if the user can view all reviews (admin moderation queue).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }
}
