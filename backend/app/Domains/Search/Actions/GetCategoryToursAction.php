<?php

namespace App\Domains\Search\Actions;

class GetCategoryToursAction
{
    public function __construct(
        protected SearchToursAction $searchToursAction
    ) {}

    public function execute(string $categorySlug, array $params): array
    {
        $params['category'] = $categorySlug;

        return $this->searchToursAction->execute($params);
    }
}
