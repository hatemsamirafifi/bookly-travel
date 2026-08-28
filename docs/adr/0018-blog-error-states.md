# Blog error/loading/empty states

- **404** for non-existent slug, draft, scheduled-future (`scheduled_at > now()`), and
  archived-never-published posts — branded 404 page via `notFound()` (matching tour detail,
  `tours/[slug]/page.tsx:46-49`).
- **410 Gone** for archived-previously-published posts (matching `GetTourDetailAction.php:33-35`).
- **422** for invalid `locale`, `slug` (regex `^[a-z0-9-]+$`), or `per_page` (via
  `assertJsonValidationErrors`, matching `SearchToursTest.php`).
- **429** rate limit with `Retry-After` header; frontend renders `SearchUnavailable`-style
  fallback (matching `search/page.tsx:67-90`).
- **Loading**: skeleton cards (reusing `LoadingSkeleton`).
- **Empty list**: "No posts yet" with a browse-tours CTA (mirroring "No tours found").
- **Preview token invalid/expired**: 403 with "Preview link expired" message.