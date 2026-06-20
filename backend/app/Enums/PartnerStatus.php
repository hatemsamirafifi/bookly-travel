<?php

namespace App\Enums;

/**
 * Partner lifecycle status (Spec 013, data-model.md §5).
 *
 * Formalizes the existing bare-string `partners.onboarding_status` column.
 * The legacy DB default is `incomplete`; Partner::canTransitionTo() normalizes
 * `incomplete` to Pending so pre-existing rows remain governable without a
 * schema change.
 */
enum PartnerStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
}