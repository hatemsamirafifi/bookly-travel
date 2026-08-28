# Blog caching: Redis 5min list/category + uncached detail + 1hr sitemap

- **Blog list + category listing**: cached 5min (300s) under cache tags so invalidation can
  target them precisely. List entries:
  `Cache::tags(['blog','blog_list'])->remember('bookly:blog:list:{locale}:{hash}', 300)`;
  category entries:
  `Cache::tags(['blog','blog_categories'])->remember('bookly:blog:category:{slug}:{locale}:{hash}', 300)`.
  Frontend `revalidate: 300`.
- **Blog detail**: uncached SSR (no backend cache, no frontend revalidate) — matching tour
  detail exactly. Fresh content on every request; the detail is cache-bust-free.
- **Sitemap**: extend the existing `SitemapController` Redis cache (3600s/1hr) to include
  published blog URLs; regenerate on publish/archive via a queued job (spec 006:107).
- **Preview route**: bypasses all caches entirely (never cached, never in sitemap).

Invalidation: on publish/update/archive, `UpdateBlogPostAction` dispatches
`InvalidateBlogCacheJob`, which flushes the `blog`/`blog_list`/`blog_categories` tags on
Redis/memcached. On cache stores without tag support it falls back to per-key
`Cache::forget()` of the known `bookly:blog:*` keys — never `Cache::flush()` (CR-009: that
wipes sessions/rate limits). `RegenerateSitemapJob` is dispatched alongside it. List and
category entries MUST be stored with these tags, or the tag flush is a no-op and stale
content is served for the full 300s TTL.