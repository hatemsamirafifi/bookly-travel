# Search

<!-- EGM contract -->

## Overview

Localized server-rendered tour search with filters, sorting, and pagination.

## Interface

- `/{locale}/search` accepts `q`, `page`, `category`, `location`, `price_min`, `price_max`, `duration`, `date`, and `sort`. It sends these to `searchTours` using `parseSort` and integer conversion for page and prices. [Search query](./page.tsx#L45)
- [loading.tsx](./loading.tsx#L5) declares `default SearchLoading`.
- [page.tsx](./page.tsx#L30) declares `generateMetadata`, `default SearchPage`.

## Invariants

- Rate limits and HTTP 503 render `SearchUnavailable`, with retry timing passed through for rate limits. Other errors use the localized load error. [Error handling](./page.tsx#L71) [Unavailable state](./page.tsx#L106)

## Side Effects

- Fetches search results through `searchTours` with a requested revalidation interval of 300 seconds. [Search fetch](./page.tsx#L58) [Fetch policy](../../../../lib/api/search.ts#L29)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->
