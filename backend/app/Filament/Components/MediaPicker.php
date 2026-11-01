<?php

namespace App\Filament\Components;

use App\Domains\Media\Models\MediaAsset;
use Filament\Forms\Components\Select;

/**
 * Reusable form field for picking an existing media asset (T-E02).
 *
 * A searchable Select backed by the media library. By default it stores
 * the selected asset's `storage_key`; `->storesAttribute('id')` stores
 * the asset id instead. Search matches filename, localized title,
 * tag names, and UUID.
 */
class MediaPicker extends Select
{
    protected string $stores = 'storage_key';

    public function storesAttribute(string $attribute): static
    {
        $this->stores = $attribute;

        return $this;
    }

    public static function make(string $name): static
    {
        $static = parent::make($name);

        $static->stores = 'storage_key';

        return $static
            ->label('Media asset')
            ->options(function (): array {
                return MediaAsset::query()
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get()
                    ->mapWithKeys(fn (MediaAsset $asset) => [$asset->storage_key => static::optionLabel($asset)])
                    ->all();
            })
            ->searchable()
            ->getSearchResultsUsing(function (string $search): array {
                return static::searchOptions($search);
            })
            ->getOptionLabelUsing(function ($value): ?string {
                $asset = MediaAsset::query()
                    ->when(ctype_digit((string) $value), fn ($q) => $q->orWhere('id', (int) $value))
                    ->where('storage_key', $value)
                    ->first();

                return $asset ? static::optionLabel($asset) : null;
            });
    }

    /** @return array<int, string> */
    private static function searchOptions(string $search): array
    {
        $query = MediaAsset::query()
            ->when(filled($search), fn ($q) => $q->where(function ($q) use ($search): void {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhereRaw('LOWER(title::text) LIKE ?', ['%' . mb_strtolower($search) . '%'])
                    ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('created_at')
            ->limit(50);

        return $query->get()
            ->mapWithKeys(fn (MediaAsset $asset) => [$asset->storage_key => static::optionLabel($asset)])
            ->all();
    }

    private static function optionLabel(MediaAsset $asset): string
    {
        $title = collect((array) $asset->title)->first(fn ($value) => filled($value));

        return filled($title) ? "{$title} ({$asset->original_filename})" : $asset->original_filename;
    }
}
