<?php

namespace App\Filament\Components;

use App\Domains\Media\Models\MediaAsset;
use Filament\Forms\Components\Select;

/**
 * Reusable form field for picking an existing media asset (T-E02).
 *
 * A searchable Select backed by the media library. By default it stores
 * the selected asset's `storage_key`; `->storesAttribute('id')` stores
 * the asset id instead. Option keys always follow the configured
 * attribute so saved state round-trips through options, search, and
 * label resolution. Search matches filename, localized title, tag
 * names, and UUID.
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
            ->options(function () use ($static): array {
                return $static->allOptions(100);
            })
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => $static->searchOptions($search))
            ->getOptionLabelUsing(fn ($value): ?string => $static->resolveOptionLabel($value));
    }

    /** @return array<int, string> */
    private function allOptions(int $limit): array
    {
        return MediaAsset::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (MediaAsset $asset) => [$asset->getAttribute($this->stores) => static::optionLabel($asset)])
            ->all();
    }

    /** @return array<int, string> */
    private function searchOptions(string $search): array
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
            ->mapWithKeys(fn (MediaAsset $asset) => [$asset->getAttribute($this->stores) => static::optionLabel($asset)])
            ->all();
    }

    private function resolveOptionLabel(mixed $value): ?string
    {
        $asset = MediaAsset::query()
            ->where($this->stores, $value)
            ->first();

        return $asset ? static::optionLabel($asset) : null;
    }

    private static function optionLabel(MediaAsset $asset): string
    {
        $title = collect((array) $asset->title)->first(fn ($value) => filled($value));

        return filled($title) ? "{$title} ({$asset->original_filename})" : $asset->original_filename;
    }
}
