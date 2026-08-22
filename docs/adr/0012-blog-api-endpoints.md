# Blog API endpoints: 4 public endpoints mirroring tours

Spec 016 exposes four public read-only endpoints under `/api/public/blog`:
- `GET /api/public/blog` — paginated list (`?category=&page=&per_page=&locale=`), response `{ data, meta: {current_page,last_page,per_page,total} }`.
- `GET /api/public/blog/{slug}` — detail with `data.seo` block (`meta_title`, `meta_description`, `canonical_url`, `hreflang`), related published tours (via TourCardTransformer), author metadata, and `reading_time` (estimated whole minutes from body word count, min 1; resolved in Spec 016 clarification, 2026-08-22).
- `GET /api/public/blog/category/{slug}` — category detail + paginated posts in that category.
- `GET /api/public/blog/{slug}/preview?token={signed}` — preview of any-status post with a valid time-limited token.

Rate limiters `blog` (120/min) and `blog_detail` (120/min) added to `AppServiceProvider`.
All endpoints reuse the `LocaleRequest` required-`?locale=` validation convention. This
mirrors the tours API pattern (specs/010/contracts/tours-api.md) exactly.