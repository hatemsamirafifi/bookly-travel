<?php

namespace App\Enums;

/**
 * Tour lifecycle status (Spec 013, data-model.md §5).
 *
 * Formalizes the existing bare-string `tours.status` column. Filament
 * governance actions assert `Tour::canTransitionTo()` before mutating.
 */
enum TourStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
