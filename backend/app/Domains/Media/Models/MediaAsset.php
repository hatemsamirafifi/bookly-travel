<?php

namespace App\Domains\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $table = 'media_assets';

    protected $fillable = [
        'uuid',
        'folder_id',
        'disk',
        'storage_key',
        'original_filename',
        'filename',
        'extension',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'checksum_sha256',
        'variants',
        'alt_text',
        'title',
        'caption',
        'focal_point',
        'processing_status',
        'visibility',
        'uploaded_by',
    ];

    protected $casts = [
        'variants' => 'array',
        'alt_text' => 'array',
        'title' => 'array',
        'caption' => 'array',
        'focal_point' => 'array',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'folder_id' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(MediaUsage::class, 'media_asset_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(MediaTag::class, 'media_asset_tag', 'media_asset_id', 'media_tag_id');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    /**
     * Public URL of the original file or a generated variant.
     */
    public function publicUrl(string $variant = 'original'): string
    {
        if ($variant !== 'original') {
            $variantData = $this->variants[$variant] ?? null;

            if (is_array($variantData) && filled($variantData['key'] ?? null)) {
                return Storage::disk($this->disk)->url($variantData['key']);
            }
        }

        return Storage::disk($this->disk)->url($this->storage_key);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
