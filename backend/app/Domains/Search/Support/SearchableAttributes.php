<?php

namespace App\Domains\Search\Support;

/**
 * Maps a locale to the Meilisearch `attributesToSearchOn` for the public tour
 * index, per search-api.md:145 — the `locale` controls which language fields
 * are searched (plus shared location/category fields), it is not a filter on
 * indexed data.
 *
 * The field list mirrors `Tour::toSearchableArray()`.
 */
class SearchableAttributes
{
    public static function forLocale(string $locale): array
    {
        return match ($locale) {
            'es' => ['title_es', 'description_es', 'highlights_es', 'location', 'category_name'],
            'it' => ['title_it', 'description_it', 'highlights_it', 'location', 'category_name'],
            default => ['title_en', 'description_en', 'highlights_en', 'location', 'category_name'],
        };
    }
}
