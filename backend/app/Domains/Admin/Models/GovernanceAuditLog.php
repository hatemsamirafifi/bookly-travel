<?php

namespace App\Domains\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Canonical append-only governance audit trail (Spec 013, FR-011/FR-012).
 *
 * Every admin governance action (tour/partner/booking/review/settings/CMS)
 * writes exactly one row here via GovernanceAuditService. Rows are immutable:
 * the booted `updating`/`deleting` hooks return false to enforce append-only
 * semantics at the model layer. UPDATED_AT is null because there is no
 * "edited" state — only `created_at` is set, once.
 *
 * Actor and target use Laravel morph maps (registered in AppServiceProvider):
 * `admin` => User, `tour` => Tour, `partner` => Partner, `booking` => Booking,
 * `review` => Review, `static_page` => StaticPage. `setting` is a plain
 * target_type string with a null target_id (settings are not Eloquent models).
 */
class GovernanceAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'governance_audit_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'actor_id' => 'integer',
        'target_id' => 'integer',
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        // Append-only enforcement (FR-012 immutability). Block every update
        // and delete so the trail is tamper-evident at the model layer.
        static::updating(function () {
            return false;
        });

        static::deleting(function () {
            return false;
        });
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
