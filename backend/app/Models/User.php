<?php

namespace App\Models;

use App\Domains\Admin\Models\AdminPermission;
use App\Domains\Partner\Models\Partner;
use App\Domains\Wishlist\Models\Wishlist;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
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
        'role',
        'locale',
        'first_name',
        'last_name',
        'phone',
        'preferred_currency',
        'marketing_emails',
        'avatar_url',
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
            'marketing_emails' => 'boolean',
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
     * Wishlist items for this user.
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        if ($this->first_name || $this->last_name) {
            return trim("{$this->first_name} {$this->last_name}");
        }

        return $this->name;
    }

    /**
     * Audit logs representing authentication events for this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuthAuditLog::class);
    }

    public function partner()
    {
        return $this->hasOne(Partner::class);
    }

    /**
     * Per-action admin permission flags (Spec 013, data-model.md §2).
     * One row per admin user; null for non-admins.
     */
    public function adminPermission()
    {
        return $this->hasOne(AdminPermission::class);
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }
}
