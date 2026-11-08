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

    public function fileExists(): bool
    {
        try {
            return filled($this->storage_key) && Storage::disk($this->disk)->exists((string) $this->storage_key);
        } catch (\Throwable) {
            return false;
        }
    }

    public function isUsable(): bool
    {
        return $this->processing_status === self::STATUS_READY
            && $this->fileExists()
            && ! $this->trashed();
    }

    public static function missingImagePlaceholderSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="100" viewBox="0 0 160 100" fill="none"><rect width="160" height="100" rx="6" fill="#F1F5F9" stroke="#E2E8F0" stroke-width="1"/><circle cx="68" cy="42" r="4" fill="#94A3B8"/><path d="M52 62L66 48L74 56L82 46L98 62H52Z" fill="#94A3B8"/><text x="80" y="78" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif" font-size="11" font-weight="500" fill="#64748B">Missing image</text></svg>';
    }

    public static function missingImagePlaceholderUrl(): string
    {
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode(static::missingImagePlaceholderSvg());
    }

    public function previewUrl(): string
    {
        if (! $this->fileExists()) {
            return static::missingImagePlaceholderUrl();
        }

        if (! $this->isImage()) {
            return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="160" height="100" viewBox="0 0 160 100" fill="none"><rect width="160" height="100" rx="6" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="1"/><text x="80" y="55" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif" font-size="13" font-weight="600" fill="#64748B">' . strtoupper($this->extension ?: 'DOC') . '</text></svg>');
        }

        return $this->publicUrl('thumbnail');
    }

    /**
     * Public URL of the original file or a generated variant.
     */
    public function publicUrl(string $variant = 'original'): string
    {
        if ($variant !== 'original') {
            $variantData = $this->variants[$variant] ?? null;

            if (is_array($variantData) && filled($variantData['key'] ?? null)) {
                return $this->formatUrl((string) $variantData['key']);
            }
        }

        return $this->formatUrl((string) $this->storage_key);
    }

    private function formatUrl(?string $key): string
    {
        if (! filled($key)) {
            return '';
        }

        $url = Storage::disk($this->disk)->url($key);

        if (str_starts_with($url, 'http://localhost/')) {
            return substr($url, strlen('http://localhost'));
        }

        return $url;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
