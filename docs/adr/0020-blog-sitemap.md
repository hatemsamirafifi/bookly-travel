# Blog sitemap: extend SitemapController

The existing `backend/app/Domains/Search/Controllers/Public/SitemapController.php` is
extended to stream published blog posts (`status='published' AND scheduled_at <= now()`)
via `chunkById(500)` with hreflang alternates for all 3 locales, alongside blog category
URLs. Reuses the Redis 3600s cache key and `Cache-Control` header.

A sitemap-regeneration queued job is dispatched on publish/archive (per spec 006:107),
flushing the cache so the next request rebuilds it. Draft/scheduled-future/archived posts
are excluded.

We rejected a separate frontend `sitemap.ts` because it would add a second sitemap source
while `robots.ts` already references a single `/sitemap.xml`, and the backend
SitemapController is the established, tested source.