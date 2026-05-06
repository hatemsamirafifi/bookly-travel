<?php

namespace App\Domains\Search\Actions;

class GetDestinationToursAction
{
    public function __construct(
        protected SearchToursAction $searchToursAction
    ) {}

    public function execute(string $locationSlug, array $params): array
    {
        $params['location'] = $locationSlug;

        return $this->searchToursAction->execute($params);
    }
}
