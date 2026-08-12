<?php

namespace App\Policies;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Reviews\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function __construct(private readonly AdminAuthorizationService $authz) {}

    /**
     * Determine if the user can moderate (hide/reinstate) reviews.
     * Per-action flag via AdminAuthorizationService (Spec 013, FR-002).
     */
    public function manage(User $user, Review $review): bool
    {
        return $this->authz->can($user, 'moderate_reviews');
    }

    /**
     * Determine if the user can view all reviews (admin moderation queue).
     */
    public function viewAny(User $user): bool
    {
        return $this->authz->can($user, 'moderate_reviews');
    }
}
