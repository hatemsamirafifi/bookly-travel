# Research: Blog and Travel Insights

**Feature**: 016-blog-travel-insights | **Date**: 2026-08-22

Phase 0 research resolving all technical unknowns by inspecting the live codebase. Each finding
includes the decision, rationale, and exact file/line references the implementation must follow.

---

## R1. JSONB-localized column pattern (the StaticPage precedent)

**Decision**: Blog posts use JSONB-localized columns (`title`, `body`, `excerpt`,
`meta_description`) keyed by locale, exactly mirroring `StaticPage` — NOT the separate-translations-
table pattern used by `Tour`.

**Rationale**: ADR-0001 explicitly mandates mirroring the `StaticPage` CMS pattern. `StaticPage`
is the canonical JSONB-localized model in the codebase. Tour uses a separate `tour_translations`
table because tours have complex queryable translated fields; blog posts are simpler editorial
content where JSONB columns + `array` casts are sufficient and match the ratified ADR.

**Codebase evidence**:
- `backend/app/Domains/Admin/Models/StaticPage.php:38-44` — `'title' => 'array'`, `'body' => 'array'`, `'meta_description' => 'array'`
- `backend/database/migrations/2026_06_20_100003_create_static_pages_table.php:11-24` — `$table->jsonb('title')`, `$table->jsonb('body')`, `$table->jsonb('meta_description')->nullable()`
- `StaticPage.php:49` — `public const LOCALES = ['en', 'es', 'it'];`
- `StaticPage.php:76-99` — `contentFor()` / localized fallback accessor: `Arr::has` + `filled()` then `collect($values)->first(fn ($value) => filled($value))`
- Tour translations table (the rejected alternative): `backend/app/Models/Tour.php:361-364` — `hasMany(TourTranslation::class)`

**Alternatives considered**:
- Separate `blog_post_translations` table (Tour pattern) — rejected: over-engineering for editorial content; ADR-0001 mandates StaticPage pattern.

---

## R2. Publication lifecycle + `published_at` stamping

**Decision**: Status is a plain `string` column (`draft`/`published`/`archived`) + nullable
`scheduled_at` timestamp + nullable `published_at` timestamp. `published_at = published_at ?? now()`
is stamped inside `UpdateBlogPostAction` (not the model), preserving the original on re-publish.

**Rationale**: Matches `UpdateStaticPageAction` exactly. The action is also where audit logging
happens, keeping all mutation side-effects in one audited transaction.

**Codebase evidence**:
- `backend/app/Domains/Admin/Actions/UpdateStaticPageAction.php:50-55` — `if ($willPublish) { $page->status = StaticPage::STATUS_PUBLISHED; $page->published_at = $page->published_at ?? now(); }`
- `StaticPage.php:22-24` — `STATUS_DRAFT = 'draft'`, `STATUS_PUBLISHED = 'published'`
- Migration: `$table->string('status', 20)->default('draft')`, `$table->timestamp('published_at')->nullable()`, `$table->index(['status', 'published_at'])`

**Blog additions**: `archived` status (not in StaticPage) + `scheduled_at` column (not in StaticPage).
A post is publicly visible iff `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())`.

**Alternatives considered**:
- Enum column — rejected: codebase uses plain `string` for status everywhere (Tour, StaticPage).
- Model-level stamping — rejected: action-level keeps audit + stamping in one transaction.

---

## R3. 404 vs 410 Gone gating

**Decision**: The detail action returns 404 for non-existent/draft/scheduled-future/archived-never-
published, and 410 Gone for archived-previously-published (checking `published_at !== null`).

**Rationale**: Exactly mirrors `GetTourDetailAction` — the only precedent for 410 in the codebase.

**Codebase evidence**:
- `backend/app/Domains/Search/Actions/GetTourDetailAction.php:21-36`:
```php
if ($tour->status !== 'published') {
    $code = $tour->status === 'archived' && $tour->published_at !== null
        ? 410
        : 404;
    throw new HttpResponseException(response()->json(['message' => $message], $code));
}
```
- Frontend: `frontend/src/lib/api/client.ts:143-148` — `GoneError` class already defined (410 handler).

**Blog adaptation**: Blog adds `scheduled_at` — a `published` post with `scheduled_at > now()` also
returns 404 (not yet visible). The 410 check is `status === 'archived' && published_at !== null`.

---

## R4. SEO block (canonical + hreflang)

**Decision**: The detail action builds a `data.seo` block with `meta_title`, `meta_description`,
`canonical_url` (self-canonical per locale), and `hreflang` map (all 3 locales), exactly mirroring
`GetTourDetailAction::buildSeoMetadata`.

**Rationale**: ADR-0010 mandates self-canonical per locale + hreflang alternates matching the tour
detail precedent.

**Codebase evidence**:
- `GetTourDetailAction.php:123-144`:
```php
$canonical = "{$baseUrl}/{$locale}/tours/{$tour->slug}";
foreach (config('app.supported_locales', ['en', 'es', 'it']) as $lang) {
    $hreflang[$lang] = "{$baseUrl}/{$lang}/tours/{$tour->slug}";
}
return ['meta_title' => ..., 'meta_description' => ..., 'canonical_url' => $canonical, 'hreflang' => $hreflang];
```
- Frontend consumption: `frontend/src/app/[locale]/(public)/tours/[slug]/page.tsx:20-23` — `alternates: { canonical: data.seo.canonical_url, languages: data.seo.hreflang }`
- Frontend type: `frontend/src/lib/api/types.ts:80-85` — `SeoMetadata = { meta_title, meta_description, canonical_url, hreflang: Record<string,string> }`

**Blog adaptation**: Replace `/tours/` with `/blog/` in URL paths.

---

## R5. Partial-translation fallback

**Decision**: When the requested locale's content is missing, return EN content with
`translation_warning: 'partial_translation'` in the API response. EN title + body are required to
publish.

**Rationale**: ADR-0003 mandates matching the Tour fallback behavior.

**Codebase evidence**:
- `Tour.php:376-389` — `displayTitle()`: `$translations->firstWhere('locale', $locale) ?? $translations->firstWhere('locale', $fallback) ?? $translations->first()`
- `GetTourDetailAction.php:116-118` — emits `translation_warning: 'partial_translation'` when fallback used
- `StaticPage.php:76-99` — `contentFor()` does the same JSONB fallback: `Arr::has` + `filled()` + `collect()->first(fn ($value) => filled($value))`

**Blog adaptation**: Use `contentFor()`-style accessor on JSONB columns; emit the same
`translation_warning` field in the detail transformer.

---

## R6. Sitemap extension

**Decision**: Extend the existing `SitemapController` to stream published blog posts + blog
categories via `chunkById(500)` with hreflang alternates, reusing the Redis 3600s cache +
`Cache-Control` header. A queued job flushes the sitemap cache on publish/archive.

**Rationale**: ADR-0020 mandates extending the existing backend SitemapController, not creating a
separate frontend `sitemap.ts`.

**Codebase evidence**:
- `backend/app/Domains/Search/Controllers/Public/SitemapController.php:17-19` — `CACHE_TTL = 3600`, `CACHE_KEY = 'bookly:sitemap:xml'`
- `:25-27` — `->header('Cache-Control', 'public, max-age=3600')`
- `:54-66` — `Tour::published()->select(['id', 'slug'])->chunkById(500, ...)` with `renderUrl()` + `alternates()`
- `:102-110` — `alternates()` builds `<xhtml:link rel="alternate" hreflang="...">` entries

**Blog adaptation**: Add a blog section streaming `BlogPost::published()->where('scheduled_at', '<=', now())` and a blog-categories section. The cache flush job calls `Cache::forget('bookly:sitemap:xml')`.

---

## R7. Governance audit logging

**Decision**: Every admin governance action (publish, update, archive) calls
`GovernanceAuditService::log()` with action keys `blog.publish` / `blog.update` / `blog.archive`,
inside the same DB transaction as the state change.

**Rationale**: ADR-0013 mandates reusing the existing audit service with `blog.*` keys mirroring
the `cms.*` precedent.

**Codebase evidence**:
- `backend/app/Domains/Admin/Services/GovernanceAuditService.php:34-42` — `log(User $actor, string $action, ?Model $target, ?array $before, ?array $after, array $metadata, ?string $targetType)`
- `:43-55` — writes `actor_type/actor_id/action/target_type/target_id/before_state/after_state/metadata` (JSONB for state + metadata)
- Append-only: `GovernanceAuditLog.php:38-49` — booted `updating`/`deleting` hooks return false
- `UpdateStaticPageAction.php:60-62` — `$action = $willPublish && $before['status'] !== 'published' ? 'cms.publish' : 'cms.update'`

**Blog adaptation**: `UpdateBlogPostAction` determines `blog.publish` (status transitioned to published) vs `blog.update` (content/status change without first publication) vs `blog.archive` (status → archived). Register morph map alias `'blog_post' => BlogPost::class` in `AppServiceProvider::boot()`.

---

## R8. Authorization — `manage_blog` flag

**Decision**: Add `'manage_blog'` to `AdminAuthorizationService::FLAGS` alongside `manage_cms`.
`BlogPostPolicy` delegates every method to `$this->authz->can($user, 'manage_blog')`. Register via
`Gate::policy(BlogPost::class, BlogPostPolicy::class)` in `AppServiceProvider`.

**Rationale**: ADR-0013 mandates a distinct flag so blog and static-page permissions can be granted
independently.

**Codebase evidence**:
- `backend/app/Domains/Admin/Services/AdminAuthorizationService.php:22-32` — `FLAGS = ['manage_tours', 'manage_partners', ..., 'manage_cms', 'view_audit_log']`
- `:34-43` — `can(User $user, string $permission): bool` checks `role === 'admin'` + `flags[$permission]`
- `backend/app/Filament/Resources/StaticPageResource.php` — `StaticPagePolicy` delegates: `$this->authz->can($user, 'manage_cms')`
- `AppServiceProvider.php:42-47` — `Gate::policy(StaticPage::class, StaticPagePolicy::class)` + morph map at `:59`

---

## R9. LocaleRequest validation

**Decision**: All public blog endpoints extend `LocaleRequest` (or accept the same `?locale=` rule)
requiring `locale` ∈ supported locales, returning 422 on missing/invalid.

**Rationale**: ADR-0012 mandates mirroring the tours API `LocaleRequest` convention.

**Codebase evidence**:
- `backend/app/Http/Requests/Public/Search/LocaleRequest.php:19-26`:
```php
$locales = implode(',', config('app.supported_locales', ['en', 'es', 'it']));
return ['locale' => ['required', "in:{$locales}"]];
```
- Test: `SearchToursTest.php:10-20` — `assertStatus(422)->assertJsonValidationErrors(['locale'])`

---

## R10. Rate limiters

**Decision**: Register `blog` (120/min) and `blog_detail` (120/min) rate limiters in
`AppServiceProvider`, mirroring the existing `listing`/`detail` pattern. Sitemap reuses the existing
`sitemap` (10/min) limiter.

**Rationale**: ADR-0012 + FR-018 mandate 120/min for listing+detail, 10/min for sitemap.

**Codebase evidence**:
- `backend/app/Providers/AppServiceProvider.php:64-127`:
```php
RateLimiter::for('listing', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
RateLimiter::for('sitemap', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
```

---

## R11. TourCardTransformer reuse for related tours

**Decision**: Related tours on the blog detail are transformed via the existing
`TourCardTransformer`, rendered with the existing `TourCard` frontend component (max 6, ordered by
`sort_order`).

**Rationale**: ADR-0007 mandates reusing `TourCardTransformer` + existing tour card components.

**Codebase evidence**:
- `backend/app/Domains/Search/Transformers/TourCardTransformer.php:20-43` — outputs `id, slug, title, location, category, duration_label, price{amount,currency,formatted}, rating{average,count}, cover_image_url, group_size, next_available_date`
- `frontend/src/components/search/TourCard.tsx:10-13` — `interface TourCardProps { tour: TourCardType; locale: string }`
- Locale fallback in transformer: `:17-18` — `firstWhere('locale', $locale) ?? firstWhere('locale', 'en')`

---

## R12. Pest test conventions

**Decision**: Backend tests live in `backend/tests/Feature/Blog/` and mirror the `Search/` suite:
`Model::create([...])` without factories, `assertJsonValidationErrors`, `beforeEach Cache::flush()`
for cached endpoints. Shared helpers `makeBlogPost()` + `makeBlogCategory()` added to
`backend/tests/Pest.php`.

**Rationale**: ADR-0021 mandates mirroring the Search test suite.

**Codebase evidence**:
- `backend/tests/Feature/Search/` — 10 test files (SearchToursTest, TourDetailTest, SitemapTest, etc.)
- `backend/tests/Pest.php:68-78` — `makePartner()` helper
- `:143-167` — `makeSearchableTour()` helper (creates Partner + Category + Tour)
- `:169-184` — `addTranslation()` helper
- `SitemapTest.php:20` — `beforeEach(function () { Cache::flush(); });`
- `SearchToursTest.php:8` — `uses(RefreshDatabase::class);` (auto-applied via Pest.php:26-28)
- `TourDetailTest.php:34-46` — `Tour::create([...])` direct, no factory

---

## R13. Filament Resource conventions

**Decision**: `BlogPostResource` + `BlogCategoryResource` in `App\Filament\Resources` under a
`Content` nav group. Body edited via Filament `RichEditor` (HTML output, sanitized on input). Per-
locale fields built via a `localizedSchema()` helper producing one Section per locale (matching
`StaticPageResource`). Policy gates actions via `manage_blog`.

**Rationale**: ADR-0001 + ADR-0011 + ADR-0013 mandate Filament authoring with RichEditor.

**Codebase evidence**:
- `backend/app/Filament/Resources/StaticPageResource.php:34` — `$navigationGroup = 'Platform'` (blog uses `Content` per ADR-0005)
- `:46-51` — `TextInput::make('slug')->unique(...)`
- `:77-82` — `localizedSchema()` helper, one Section per locale
- `:63` — `Textarea::make("body_{$locale}")` (blog replaces with `RichEditor::make("body_{$locale}")`)
- `TourResource.php:116` — action gating: `auth()->user()?->can('publish', $record)`
- `TourResource.php:158,179` — bulk action gating: `app(AdminAuthorizationService::class)->can(auth()->user(), 'manage_tours')`
- `AppServiceProvider.php:47` — `Gate::policy(StaticPage::class, StaticPagePolicy::class)`

**Blog adaptation**: Use `RichEditor::make("body_{$locale}")->columnSpanFull()` instead of Textarea.
Add `is_featured` toggle, `scheduled_at` datetime picker, `author_id` select, related-tours
repeater (multi-select with sort_order), cover image upload. Preview token generation action on the
Edit page.

---

## R14. Frontend page conventions

**Decision**: Blog pages are async server components under `app/[locale]/(public)/blog/`. They call
domain API clients (`lib/api/blog.ts`), use `generateMetadata` reading `data.seo`, call `notFound()`
on `NotFoundError`, render JSON-LD via `StructuredData` components, and reuse `EmptyState` /
`LoadingSkeleton` / `Pagination` from `components/ui/` and `components/search/`.

**Rationale**: Matches the tour listing/detail/category page pattern exactly.

**Codebase evidence**:
- `frontend/src/app/[locale]/(public)/tours/[slug]/page.tsx:12-37` — `generateMetadata` reads `data.seo`, sets `alternates: { canonical, languages: hreflang }`
- `:42-51` — `try/catch` → `if (e instanceof NotFoundError) notFound()`
- `:55` — `<TouristTripSchema tour={data} locale={locale} />`
- `frontend/src/app/[locale]/(public)/search/page.tsx:42-66` — async server component fetch
- `:84-90` — `<SearchUnavailable retryAfter={...} />` on `RateLimitError`
- `frontend/src/app/[locale]/(public)/categories/[slug]/page.tsx:32-49` — category page delegates to `ListingPage`
- `frontend/src/components/ui/EmptyState.tsx:4-12` — `interface EmptyStateProps { title, description?, icon?, cta? }`
- `frontend/src/components/ui/LoadingSkeleton.tsx:1-4` — `interface LoadingSkeletonProps { count?, variant? }`
- `frontend/src/components/search/Pagination.tsx:5-8` — `interface PaginationProps { currentPage, lastPage }`

**Blog additions**:
- `BlogPostingSchema` + `BreadcrumbListSchema` in `StructuredData.tsx` (ADR-0015)
- `openGraph.type = 'article'` + `twitter: card = 'summary_large_image'` on detail (ADR-0019 — greenfield, no existing twitter card precedent)
- `openGraph.type = 'website'` + `twitter: card = 'summary'` on listing/category
- Preview page: `robots: { index: false, follow: false }` (ADR-0009)
- 410 handling: catch `GoneError` and render a branded "content removed" page (tour detail does not currently branch on 410 — blog should)

---

## R15. API client conventions

**Decision**: `frontend/src/lib/api/blog.ts` exports `getBlogPosts`, `getBlogPost`,
`getBlogPostPreview`, `getBlogCategory` with typed response interfaces. Public reads pass
`revalidate: 300` (listing/category); detail is uncached (no revalidate); preview is uncached +
`noindex`.

**Rationale**: Matches `lib/api/tours.ts` + `client.ts` conventions; ADR-0014 mandates 5-min
list/category cache, uncached detail + preview.

**Codebase evidence**:
- `frontend/src/lib/api/client.ts:2-5` — `API_URL = (typeof window === 'undefined' && API_INTERNAL_URL) ? API_INTERNAL_URL : NEXT_PUBLIC_API_URL`
- `:52-59` — sets `Accept-Language` from `locale`
- `:24,63-69` — opt-in `revalidate` (Next.js data cache)
- `:74-106` — error mapping: 429→`RateLimitError`, 404→`NotFoundError`, 410→`GoneError`, 422→`ValidationError`
- `frontend/src/lib/api/tours.ts:8-12` — `getTourDetail(slug, locale)` pattern
- `frontend/src/lib/api/search.ts:9` — `revalidate: 300` on public listing
- `frontend/src/lib/api/types.ts:80-85` — `SeoMetadata` shape (blog reuses)
- `:117-131` — `SearchResponse { data: TourCard[]; meta: {current_page,last_page,per_page,total} }` (blog list response mirrors this)

---

## R16. Scheduled-transition invalidation (clarification resolution)

**Decision**: A queued `PublishScheduledBlogPostJob` runs at `scheduled_at` (dispatched with
`delay($post->scheduled_at)` when a post is saved with a future `scheduled_at` + `status =
'published'`). The job re-validates the post's scheduling state, invalidates affected list/category
cache keys, and dispatches sitemap regeneration.

**Rationale**: Spec clarification (2026-08-22) resolved that scheduled-transition invalidation MUST
trigger a queued job. ADR-0004 was updated to record this. Without it, scheduled posts would be
invisible in the sitemap for up to 1 hour.

**Implementation**: On `UpdateBlogPostAction` save with `status = 'published'` + future
`scheduled_at`, dispatch `PublishScheduledBlogPostJob::dispatch($post)->delay($post->scheduled_at)`.
The job checks the post still has `status = 'published'` + `scheduled_at <= now()`, then flushes
`bookly:blog:list:{locale}:{hash}` keys + `bookly:sitemap:xml`.

---

## R17. Preview token binding (clarification resolution)

**Decision**: HMAC token bound to `slug + expires_at`. The preview endpoint verifies the token
against the requested `{slug}` before returning content. Changing a post's slug invalidates
outstanding tokens.

**Rationale**: Spec clarification (2026-08-22) + ADR-0009 (updated). Binding to slug (not post_id)
keeps verification self-contained in the request and prevents slug-rebinding attacks.

**Implementation**: `GeneratePreviewTokenAction` builds `Hash::hmac('sha256', "{$slug}|{$expiresAt}", config('app.preview_key'))`.
`GetBlogPostPreviewAction` recomputes the HMAC from the request's `{slug}` + token's `expires_at`
and compares with `hash_equals()`. TTL: 30 minutes.

---

## R18. Related-posts backfill (clarification resolution)

**Decision**: FR-012 backfills: prefer up to 3 same-category published posts (newest-first,
excluding current); if fewer than 3, fill from other categories newest-first until 3 total.

**Rationale**: Spec clarification (2026-08-22). Ensures the related-posts section is always
populated for internal linking, even for posts in small/new categories.

---

## R19. `reading_time` in detail contract (clarification resolution)

**Decision**: The detail endpoint includes `reading_time` (integer minutes ≥1, derived from body
word count) in the response.

**Rationale**: Spec clarification (2026-08-22) + ADR-0012 (updated). ADR-0017 lists reading time as
a detail-page element; the contract must supply it.

**Implementation**: `BlogPostDetailTransformer` computes `max(1, ceil(str_word_count(strip_tags($body)) / 200))`.

---

## R20. Caching strategy

**Decision**:
- List + category listing: `Cache::remember('bookly:blog:list:{locale}:{hash}', 300)` (5min), frontend `revalidate: 300`
- Detail: uncached (no backend cache, no frontend revalidate)
- Sitemap: extend existing `bookly:sitemap:xml` (3600s/1hr)
- Preview: bypasses all caches

Invalidation on publish/update/archive: `UpdateBlogPostAction` flushes affected list/category keys
+ dispatches sitemap cache flush. Scheduled-transition: `PublishScheduledBlogPostJob` does the same.

**Rationale**: ADR-0014 mandates this exact strategy, matching the tour detail (uncached) +
categories/destinations (5min) precedent.

**Codebase evidence**:
- `SitemapController.php:17-19` — `CACHE_TTL = 3600`, `CACHE_KEY = 'bookly:sitemap:xml'`
- `frontend/src/lib/api/search.ts:9` — `revalidate: 300`
- Tour detail: no `revalidate` passed (uncached)