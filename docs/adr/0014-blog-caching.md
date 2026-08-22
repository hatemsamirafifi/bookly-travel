# Blog caching: Redis 5min list/category + uncached detail + 1hr sitemap

- **Blog list + category listing**: `Cache::remember('bookly:blog:list:{locale}:{hash}', 300)`
  (5min, matching categories/destinations). Frontend `revalidate: 300`.
- **Blog detail**: uncached SSR (no backend cache, no frontend revalidate) — matching tour
  detail exactly. Fresh content on every request; the detail is cache-bust-free.
- **Sitemap**: extend the existing `SitemapController` Redis cache (3600s/1hr) to include
  published blog URLs; regenerate on publish/archive via a queued job (spec 006:107).
- **Preview route**: bypasses all caches entirely (never cached, never in sitemap).

Invalidation: on publish/update/archive, the `UpdateBlogPostAction` flushes the affected
list/category cache keys and dispatches a sitemap-regeneration queued job.