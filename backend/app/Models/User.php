<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'failed_login_count',
        'locked_until',
        'last_lockout_email_sent_at',
        'last_login_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verification_email_sent_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_lockout_email_sent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Guest identities converted into this user account.
     */
    public function guestIdentities(): HasMany
    {
        return $this->hasMany(GuestIdentity::class, 'converted_user_id');
    }

    /**
     * Audit logs representing authentication events for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuthAuditLog::class);
    }
}
