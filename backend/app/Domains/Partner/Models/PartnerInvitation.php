<?php

namespace App\Domains\Partner\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerInvitation extends Model
{
    use HasFactory;

    public const EXPIRY_DAYS = 7;

    protected $table = 'partner_invitations';

    protected $fillable = [
        'email',
        'company_name',
        'contact_person',
        'invited_by_admin_id',
        'token',
        'status',
        'expires_at',
        'consumed_at',
        'partner_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_admin_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'partner_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isConsumed(): bool
    {
        return $this->status === 'consumed';
    }

    public function isValid(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
