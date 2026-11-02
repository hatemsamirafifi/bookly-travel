<?php

namespace App\Domains\Translation\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Per-field translation pipeline state (Admin 2.0 Phase H, T-H01).
 *
 * Tracks the status and provenance of one translatable field of one record
 * in one target locale (`es`/`it`), including the English source hash the
 * current translation was produced from — the key that powers field-level
 * re-translation, manual-edit protection, and health monitoring.
 */
class TranslationFieldState extends Model
{
    use HasFactory;

    public const STATUS_MISSING = 'missing';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_TRANSLATING = 'translating';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_OUTDATED = 'outdated';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_MANUALLY_EDITED = 'manually_edited';

    public const ORIGIN_AUTO = 'auto';

    public const ORIGIN_MANUAL = 'manual';

    protected $table = 'translation_field_states';

    protected $fillable = [
        'translatable_type',
        'translatable_id',
        'locale',
        'field_name',
        'status',
        'origin',
        'source_hash',
        'translated_source_hash',
        'translated_hash',
        'provider',
        'model',
        'attempt_count',
        'last_error_code',
        'last_error_message',
        'last_translated_at',
        'manually_edited_at',
        'manually_edited_by',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'last_translated_at' => 'datetime',
        'manually_edited_at' => 'datetime',
    ];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function manualEditor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'manually_edited_by');
    }
}
