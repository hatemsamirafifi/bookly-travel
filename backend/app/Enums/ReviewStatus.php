<?php

namespace App\Enums;

/**
 * Review moderation status (Spec 013, data-model.md §5).
 *
 * Formalizes the existing bare-string `reviews.status` column.
 */
enum ReviewStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
    case Flagged = 'flagged';
}