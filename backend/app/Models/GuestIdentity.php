<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'name',
        'phone',
        'converted_user_id',
        'anonymized_at',
    ];

    protected function casts(): array
    {
        return [
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * The user this guest identity was converted into (if applicable).
     */
    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id');
    }

    /**
     * Scope a query to only include active (not anonymized) guest identities.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('anonymized_at');
    }

    /**
     * Scope a query to only include guest identities ready for anonymization.
     */
    public function scopeAnonymizable(Builder $query): void
    {
        // Ready for anonymization if no related future bookings and last booking > 24m ago.
        // The business logic for identifying these is usually handled in the job,
        // but this scope acts as a base filter (not converted, not already anonymized).
        $query->whereNull('anonymized_at')
              ->whereNull('converted_user_id');
    }

    /**
     * Convert this guest identity into a registered user account.
     */
    public function convertToUser(User $user): void
    {
        $this->converted_user_id = $user->id;
        // Optionally mark it anonymized immediately or keep the record intact
        $this->saveQuietly();

        event(new \App\Domains\Auth\Events\GuestConvertedToAccount($user));
    }

    /**
     * Anonymize the guest identity's PII.
     */
    public function anonymize(): void
    {
        $this->update([
            'email' => 'anonymized_' . $this->id . '@example.com',
            'name' => 'Anonymized Guest',
            'phone' => null,
            'anonymized_at' => now(),
        ]);
    }
}
