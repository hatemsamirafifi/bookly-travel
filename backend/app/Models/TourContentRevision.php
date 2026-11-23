<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One immutable full-content snapshot of a tour revision (ADR 0024, design v3 §3).
 *
 * Snapshot and identity attributes (`tour_id`, `revision_number`, `kind`,
 * `source_locale`, `payload`, `payload_hash`, `content_hash`,
 * `base_revision_id`) are write-once at insert: a later save rejects their
 * mutation because a payload is never rewritten under a reviewed ID. Workflow
 * attributes (`state`, `submitted_at`, `decided_by`, `decided_at`) only change
 * through the locked domain service. Rows are deleted only through tour
 * deletion.
 *
 * @property int $id
 * @property int $tour_id
 * @property int $revision_number
 * @property string $kind
 * @property string $state
 * @property string $source_locale
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string $content_hash
 * @property string|null $change_summary
 * @property int|null $base_revision_id
 * @property int|null $created_by
 * @property Carbon|null $submitted_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 */
class TourContentRevision extends Model
{
    public const KIND_LEGACY_BASELINE = 'legacy_baseline';

    public const KIND_PARTNER_EDIT = 'partner_edit';

    public const KIND_ADMIN_CORRECTION = 'admin_correction';

    public const KIND_PUBLICATION = 'publication';

    public const KINDS = [
        self::KIND_LEGACY_BASELINE,
        self::KIND_PARTNER_EDIT,
        self::KIND_ADMIN_CORRECTION,
        self::KIND_PUBLICATION,
    ];

    public const STATE_DRAFT = 'draft';

    public const STATE_PROPOSED = 'proposed';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_WITHDRAWN = 'withdrawn';

    public const STATE_SUPERSEDED = 'superseded';

    public const STATES = [
        self::STATE_DRAFT,
        self::STATE_PROPOSED,
        self::STATE_APPROVED,
        self::STATE_REJECTED,
        self::STATE_WITHDRAWN,
        self::STATE_SUPERSEDED,
    ];

    /** Live candidate states, bounded by the partial unique index. */
    public const LIVE_STATES = [
        self::STATE_DRAFT,
        self::STATE_PROPOSED,
    ];

    /** Snapshot/identity attributes that must never be rewritten. */
    public const IMMUTABLE_ATTRIBUTES = [
        'tour_id',
        'revision_number',
        'kind',
        'source_locale',
        'payload',
        'payload_hash',
        'content_hash',
        'change_summary',
        'base_revision_id',
    ];

    /**
     * Payload tiers as they are stored. The stored `source` tier is what a
     * partner request writes back into the existing authored
     * `translations.{sourceLocale}` shape (Spec 019 contract).
     */
    public const TIER_SOURCE = 'source';

    public const TIER_PRESENTATION = 'presentation';

    public const TIER_OPTIONS = 'options';

    public const TIER_MEDIA = 'media';

    protected $fillable = [
        'tour_id',
        'revision_number',
        'kind',
        'state',
        'source_locale',
        'payload',
        'payload_hash',
        'content_hash',
        'change_summary',
        'base_revision_id',
        'created_by',
        'submitted_at',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'revision_number' => 'integer',
        'tour_id' => 'integer',
        'base_revision_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (TourContentRevision $revision): void {
            foreach (self::IMMUTABLE_ATTRIBUTES as $attribute) {
                if ($revision->isDirty($attribute)) {
                    throw new LogicException(
                        "tour_content_revisions.{$attribute} is immutable; a replacement always inserts a new row."
                    );
                }
            }
        });

        static::deleting(function (TourContentRevision $revision): void {
            // Rows survive through tour deletion only.
            throw new LogicException('Tour content revisions are never deleted individually.');
        });
    }

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<self, $this> */
    public function baseRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_revision_id');
    }

    /**
     * Actor attribution is kept as a nullable integer: governance audit rows
     * remain the authoritative actor record and stay readable per revision.
     */
    public function createdByUser(): ?object
    {
        return $this->created_by === null ? null : app('db')->table('users')->find($this->created_by);
    }

    /** @return HasMany<self, $this> */
    public function derivedRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'base_revision_id');
    }

    /** @return HasMany<TourRevisionRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(TourRevisionRequest::class, 'revision_id');
    }

    /** @return HasMany<TourRevisionRequest, $this> */
    public function baseRequests(): HasMany
    {
        return $this->hasMany(TourRevisionRequest::class, 'base_revision_id');
    }

    public function scopeForTour(Builder $query, int $tourId): Builder
    {
        return $query->where('tour_id', $tourId);
    }

    public function scopeState(Builder $query, string $state): Builder
    {
        return $query->where('state', $state);
    }

    public function isLive(): bool
    {
        return in_array($this->state, self::LIVE_STATES, true);
    }

    public function isApproved(): bool
    {
        return $this->state === self::STATE_APPROVED;
    }
}
