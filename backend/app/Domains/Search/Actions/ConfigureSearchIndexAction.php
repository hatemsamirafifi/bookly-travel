<?php

namespace App\Domains\Search\Actions;

use Meilisearch\Client as MeilisearchClient;

class ConfigureSearchIndexAction
{
    public function __construct(
        protected MeilisearchClient $client
    ) {}

    public function execute(): void
    {
        $index = $this->client->index('tours');

        $index->updateSearchableAttributes([
            'title_en', 'title_es', 'title_it',
            'description_en', 'description_es', 'description_it',
            'location', 'category_name',
            'highlights_en', 'highlights_es', 'highlights_it',
        ]);

        $index->updateFilterableAttributes([
            'status',
            'category_slug',
            'location_slug',
            'price_amount',
            'duration_minutes',
            'available_dates',
        ]);

        $index->updateSortableAttributes([
            'price_amount',
            'average_rating',
            'created_at',
            'review_count',
        ]);

        $index->updateRankingRules([
            'words',
            'typo',
            'proximity',
            'attribute',
            'sort',
            'exactness',
        ]);
    }
}
