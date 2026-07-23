<?php

namespace App\Domains\Search\Actions;

/**
 * Lists tours scoped to a category or destination slug (spec 006 reuse
 * cleanup). Replaces the two near-identical GetCategoryToursAction /
 * GetDestinationToursAction wrappers, which differed only in the param key
 * (`category` vs `location`) they injected before delegating to
 * SearchToursAction. The scope key is allowlisted so a caller can't inject an
 * arbitrary search param.
 */
class GetScopedToursAction
{
    protected array $scopeKeys = ['category', 'location'];

    public function __construct(
        protected SearchToursAction $searchToursAction
    ) {}

    public function execute(string $scopeKey, string $slug, array $params): array
    {
        if (! in_array($scopeKey, $this->scopeKeys, true)) {
            throw new \InvalidArgumentException("Unsupported scope key: {$scopeKey}");
        }

        $params[$scopeKey] = $slug;

        return $this->searchToursAction->execute($params);
    }
}