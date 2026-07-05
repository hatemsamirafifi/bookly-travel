<?php

namespace App\Domains\Admin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-admin permission flags (Spec 013, data-model.md §2).
 *
 * One row per admin user. The single `admin` role (users.role) grants panel
 * access; the granular per-action booleans stored in `flags` grant specific
 * governance actions (manage_tours, moderate_reviews, etc.). The flags map is
 * cast to/from JSON by Eloquent.
 */
class AdminPermission extends Model
{
    protected $table = 'admin_permissions';

    protected $fillable = ['user_id', 'flags'];

    protected $casts = [
        'flags' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}