<?php

namespace App\Domains\Partner\Models;

use App\Enums\PartnerStatus;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partner extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'onboarding_status',
        'is_active',
        'invited_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'invited_by_admin' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'partner_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(PartnerProfile::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(PartnerSettings::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->hasMany(Notification::class)->whereNull('read_at');
    }

    /**
     * Guard partner lifecycle transitions (FR-006, data-model.md §5).
     *
     * Allowed: pending → approved|rejected; approved → suspended;
     * suspended → approved; rejected → pending. The legacy DB default
     * `incomplete` is normalized to Pending so existing rows are governable
     * without a schema migration.
     */
    public function canTransitionTo(PartnerStatus|string $to): bool
    {
        return static::canTransition($this->onboarding_status, $to);
    }

    public static function canTransition(PartnerStatus|string|null $from, PartnerStatus|string $to): bool
    {
        $to = $to instanceof PartnerStatus ? $to->value : (string) $to;
        $from = $from instanceof PartnerStatus ? $from->value : (string) $from;
        if ($from === 'incomplete') {
            $from = PartnerStatus::Pending->value;
        }

        $allowed = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['suspended'],
            'suspended' => ['approved'],
            'rejected' => ['pending'],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    /**
     * FR-006: remove the partner's published tours from public discovery on
     * suspension. Tours drop to draft so Tour::shouldBeSearchable() (which
     * requires status === 'published') excludes them. `is_active` mirrors the
     * lifecycle for fast filtering. The SuspendPartnerAction audits the
     * partner.approve→suspend transition; this hook only mutates tour state.
     */
    public function removeToursFromDiscovery(): void
    {
        // Save each tour individually so the Tour::saved event fires and the
        // Scout observer re-evaluates shouldBeSearchable() (draft tours are
        // removed from the search index). A bulk ->update() bypasses model
        // events and would leave suspended partners' tours stale in the index.
        $this->tours()
            ->where('status', 'published')
            ->each(function (Tour $tour) {
                $tour->status = 'draft';
                $tour->save();
            });

        $this->forceFill(['is_active' => false])->save();
    }

    /**
     * Reinstate: flip `is_active` back on. Tours are NOT auto-republished —
     * the partner must resubmit them for admin approval (pending_review),
     * preserving the governed publishing flow (FR-005).
     */
    public function restoreToursToDiscovery(): void
    {
        $this->forceFill(['is_active' => true])->save();
    }
}
