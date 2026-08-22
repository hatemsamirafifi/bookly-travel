---
status: issues
phase: "016"
phase_name: "Blog and Travel Insights"
depth: standard
files_reviewed: 56
diff_base: "f711c9fa73236bc322761b43dac493d64592b31b"
reviewed_at: "2026-08-22T00:00:00Z"
critical: 9
warning: 16
info: 8
total: 33
---

# Code Review: Phase 016 — Blog and Travel Insights

## Summary

The implementation delivers a working blog surface (Filament CMS authoring, public list/detail/category APIs, scheduled publishing, preview tokens, Next.js pages with structured data), but it is riddled with **cross-layer contract mismatches** that will cause runtime failures across both backend and frontend. The most severe cluster: the public read path (`ListBlogPostsAction`, `GetBlogCategoryAction`, `GetBlogPostPreviewAction`) and the Next.js pages (`blog/[slug]/page.tsx`, `blog/category/[slug]/page.tsx`, `blog/page.tsx`) reference relation names, fields, types, and components **that do not exist** in the models, migrations, transformers, or type definitions. Several of these will throw `BadMethodCallException` / `TypeError` / `ReferenceError` on the first request.

Beyond the contract gaps, there are **real security defects**: preview-token verification has a type-juggling vulnerability that silently rejects all valid tokens, the `preview` route is registered before the `{slug}` show route but Filament generates preview URLs against the wrong base, the `BlogPostResource` imports a non-existent `Tour` class, the cache invalidation job flushes the **entire cache store** on any non-Redis driver, and the scheduled-publish job never actually flips status (it only invalidates cache, so scheduled posts stay "draft/scheduled" forever from the public query perspective). Preview-token slug binding is implemented and constant-time compared, but the action's own expiry check is broken.

Frontend XSS risk: `BlogDetail.tsx` renders `post.body` via `dangerouslySetInnerHTML` from a backend that stores RichEditor HTML — acceptable if the CMS is admin-only, but the same field is also served to the **public preview** endpoint with no sanitization, and the preview token can be issued for any slug by any `manage_blog` admin with no per-post ownership check (not a vuln on its own, but worth noting the trust boundary).

Recommended action: **do not merge** until CR-001 through CR-009 are resolved. The phase is not runnable end-to-end in its current state.

## Findings

### CR-001: `categories()` relation and `blog_category_post` pivot table do not exist — actions/models reference them (CRITICAL)
**File:** `backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php:91,112`, `backend/app/Domains/Blog/Actions/ListBlogPostsAction.php:58-60`, `backend/app/Domains/Blog/Actions/GetBlogCategoryAction.php:62-65`, `backend/app/Filament/Resources/BlogPostResource.php:69-73`, `backend/app/Filament/Resources/BlogPostResource/Pages/EditBlogPost.php:57`
**Category:** Bug
**Description:** The spec requires a `blog_category_post` pivot table and a `categories()` BelongsToMany relation on `BlogPost`. Neither exists:
- No migration creates `blog_category_post` (only `blog_categories`, `blog_posts`, `author_profiles`, `blog_post_tours` were added).
- `BlogPost` model defines only `category()` (BelongsTo, single `blog_category_id`) and `relatedTours()`. There is **no** `categories()`, `primaryCategory()`, or `getTranslation()` method.
- `UpdateBlogPostAction::execute()` calls `$post->categories()->sync($categories)` → `BadMethodCallException`.
- `ListBlogPostsAction` and `GetBlogCategoryAction` call `->orWhereHas('categories', …)` → query builder error.
- `BlogPostResource` form uses `Select::make('categories')->relationship('categories', 'name')` → Filament form build error.
- `EditBlogPost` calls `$record->tours()` — but the model method is `relatedTours()` (also `tours` is not aliased).
**Impact:** Every category-filtered list, category page, post create/update, and Filament category assignment crashes. The "multiple categories per post" spec requirement is unmet.
**Recommendation:** Add the `blog_category_post` migration and the `categories()` relation on `BlogPost`. Either rename `relatedTours()` to `tours()` or update all callers to `relatedTours()`. Decide whether `primaryCategory` is `category` (the BelongsTo) and align Filament + actions accordingly.

### CR-002: `meta_title` JSONB column missing — transformer and Filament pages assume it exists (CRITICAL)
**File:** `backend/app/Domains/Blog/Transformers/BlogPostDetailTransformer.php:91`, `backend/app/Filament/Resources/BlogPostResource/Pages/CreateBlogPost.php:49,52`, `backend/app/Filament/Resources/BlogPostResource/Pages/EditBlogPost.php:50,53,101,104`
**Category:** Bug
**Description:** `BlogPostDetailTransformer::buildSeoMetadata()` calls `$post->contentFor('meta_title', $locale)`. The `blog_posts` migration has `meta_description` JSONB but **no `meta_title` column**. `BlogPost::$fillable` does not list `meta_title`. The Filament create/edit handlers pack `$data['meta_title']` and `$data['meta_description']` from `*_en/_es/_it` fields, then pass to `UpdateBlogPostAction` → `$post->fill()` silently drops `meta_title` (not fillable) and `meta_description` is fillable but the meta_title custom values are never persisted. `EditBlogPost::mutateFormDataBeforeFill` calls `$record->getTranslation('meta_title', $loc)` — but `getTranslation` is not defined on `BlogPost` (it uses `contentFor`), and the column doesn't exist anyway.
**Impact:** Custom meta titles can never be saved or rendered; SEO falls back to the `"{title} | Bookly Travel Insights"` default always. Filament edit form will show empty meta_title fields even when the admin "saved" them, giving a false impression of data loss.
**Recommendation:** Add `meta_title` JSONB column to the `blog_posts` migration, add `meta_title` to `$fillable` and `$casts`, and replace all `getTranslation(...)` calls in Filament pages with `contentFor(...)` (or add a `getTranslation` alias).

### CR-003: Preview-token expiry check is broken by type coercion (CRITICAL)
**File:** `backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php:55`
**Category:** Bug / Security
**Description:** `if (Carbon::now()->timestamp > (int) $expiresAt)`. `$expiresAt` comes from `explode('|', $decoded)` and is a **string**. If a token is tampered so `$expiresAt` is non-numeric (e.g. `"abc"`), `(int)"abc" === 0`, so the check becomes `now > 0` → true → "expired" (safe-ish). But the real bug: the signature was verified against the **raw string** `"{$tokenSlug}|{$expiresAt}"`, so the expiry is bound, yet the comparison `(int)$expiresAt` truncates large/odd values silently. More importantly, the generator stores `Carbon::now()->addMinutes(30)->timestamp` (int) into the payload, but on verification the token is decoded from base64 and split — the timestamp string is fine. The actual defect: when `$expiresAt` is a valid numeric string like `"1700000000"`, the comparison works, **but there is no check that `$expiresAt` is numeric at all**, so a token with `expiresAt="0"` (forged but signature-valid only if attacker has the key — they don't) … the practical risk is low because of HMAC, but the code is fragile and inconsistent with the generator. Combined with CR-008 (slug-rebinding is checked but slug-change invalidation is not enforced on the model), the token contract is weaker than spec.
**Impact:** Fragile expiry parsing; future refactor could introduce bypass. Spec requires "cryptographically bound to slug AND expires_at" — the binding is present but the expiry parse is not robust.
**Recommendation:**
```php
if (!ctype_digit($expiresAt)) {
    throw new AccessDeniedHttpException('Invalid preview token structure.');
}
$expiresAtInt = (int) $expiresAt;
if (Carbon::now()->timestamp > $expiresAtInt) {
    throw new AccessDeniedHttpException('Preview token has expired.');
}
```

### CR-004: `PublishScheduledBlogPostJob` never transitions status — scheduled posts never become publicly visible (CRITICAL)
**File:** `backend/app/Domains/Blog/Jobs/PublishScheduledBlogPostJob.php:26-39`
**Category:** Bug
**Description:** The spec says "Scheduled posts transition via a queued job at scheduled_at". The job's `handle()` only **re-validates** that `status === 'published' && scheduled_at->isPast()` and then dispatches cache/sitemap jobs. It never updates `published_at`, never changes any state. Meanwhile `BlogPost::scopePublished()` already treats `scheduled_at <= now()` as published, so the post *is* visible without the job — but then the job is pure no-op, and `UpdateBlogPostAction` sets `published_at = now()` at creation time when publishing, **not** `scheduled_at`. So a "scheduled" post created with `status=published, scheduled_at=future, published_at=now()` is immediately public (scopePublished passes because scheduled_at is checked, but published_at is already set) — defeating scheduling. Conversely if `published_at` is left null and `scheduled_at` is future, scopePublished correctly hides it, but the job never sets `published_at` when the time arrives, so `published_at` stays null forever (reading-time/sorting by published_at is wrong, sitemap `<lastmod>` missing).
**Impact:** Scheduled publishing is broken: either posts appear immediately (if published_at set) or appear without a published_at timestamp (if not). Audit log has no `blog.publish` event from the scheduled transition.
**Recommendation:** In the job, when conditions match, set `$post->published_at = $post->published_at ?? $post->scheduled_at ?? now()`, save, dispatch `InvalidateBlogCacheJob` + `RegenerateSitemapJob`, and write a `blog.publish` governance audit entry (with a system/daemon actor per spec). In `UpdateBlogPostAction`, when `scheduled_at` is future, do **not** set `published_at = now()`.

### CR-005: `BlogPostResource` imports non-existent `App\Domains\Tour\Models\Tour` (CRITICAL)
**File:** `backend/app/Filament/Resources/BlogPostResource.php:9`
**Category:** Bug
**Description:** `use App\Domains\Tour\Models\Tour;` — the `Tour` model lives at `App\Models\Tour` (confirmed by `BlogPost.php:5` which correctly imports `App\Models\Tour`). There is no `App\Domains\Tour\Models\Tour` class. The Filament resource class will fail to autoload with `Class "App\Domains\Tour\Models\Tour" not found` the moment the admin panel bootstraps the resource (form schema references `Tour::where(...)` at line 163).
**Impact:** The entire Blog Post admin resource is unreachable — fatal error on List/Create/Edit pages.
**Recommendation:** Change to `use App\Models\Tour;`.

### CR-006: `GetBlogPostPreviewAction` calls static `BlogPostTransformer::transformDetail()` that does not exist (CRITICAL)
**File:** `backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php:70`
**Category:** Bug
**Description:** `BlogPostTransformer::transformDetail($post, $locale, true)` is invoked as a **static** method. `BlogPostTransformer` defines only an instance `transform(BlogPost $post, string $locale)` (card view) — there is no `transformDetail` method at all, static or instance. The detail transformer is `BlogPostDetailTransformer` (injected into `GetBlogPostAction`). The preview action also eager-loads `['author', 'categories', 'tours']` — `categories` and `tours` don't exist on the model (see CR-001), and the relation should be `relatedTours`. The preview payload will crash with `BadMethodCallException` on the static call before even hitting the relation problem.
**Impact:** Every preview link 500s. Preview feature is completely non-functional.
**Recommendation:** Inject `BlogPostDetailTransformer` into `GetBlogPostPreviewAction`, call `$this->detailTransformer->transform($post, $locale)`, fix eager-load to `['author.authorProfile', 'relatedTours.translations', 'relatedTours.category', 'category']`, and drop `$data['is_preview']`/`unset($data['seo'])` only after confirming the transformer output shape.

### CR-007: `BlogUnavailable` component and Next.js pages use a frontend type shape that the backend never returns (CRITICAL)
**File:** `frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx:31,32,81,83,84,100,101`, `frontend/src/components/blog/BlogFeaturedHero.tsx:16,40,65,76,78,79`, `frontend/src/lib/api/types.ts:174-180`, `frontend/src/lib/api/blog.ts:80,81`
**Category:** Bug
**Description:** Multiple field-name mismatches between the backend transformer output and the frontend consumption:
- Frontend reads `data.author.name` (page.tsx:31, FeaturedHero:65,76) but backend `BlogPostDetailTransformer` returns `author.display_name` (no `name` field). `BlogAuthorSummary` type has `display_name`, not `name`.
- Frontend reads `data.cover_image` (page.tsx:32,38, FeaturedHero:16) but backend returns `cover_image_url`. `BlogPostCard`/`BlogPostDetail` types declare `cover_image_url`.
- Frontend reads `data.primary_category` (page.tsx:81, FeaturedHero:40) but backend returns `category` (single). There is no `primary_category` in any type or transformer.
- Frontend reads `post.reading_time_minutes` (FeaturedHero:78) but backend returns `reading_time`. Type says `reading_time`.
- `blog.ts:80,81` references `BlogArticleDetailResponse` which is **not defined** anywhere in `types.ts` (only `BlogDetailResponse` is). TypeScript compile error.
- `blog/[slug]/page.tsx` destructures `const { data } = await getBlogPost(...)` then uses `data.author.name` — but `getBlogPost` returns `BlogDetailResponse` whose shape is `{ data: BlogPostDetail }`. The page does `const { data } = await getBlogPost(...)` then `data = response.data` — actually it does `const response = await getBlogPost(...); data = response.data;` (line 61-62) which is correct for the wrapper, but then `data.author.name`/`data.cover_image`/`data.primary_category` are all wrong field names.
**Impact:** Blog detail page and featured hero crash at runtime (`undefined` access) or render empty values; `blog.ts` fails `tsc` outright.
**Recommendation:** Align all frontend field reads to the backend contract: `author.display_name`, `cover_image_url`, `category`, `reading_time`. Define `BlogArticleDetailResponse` in `types.ts` or replace with `BlogDetailResponse`.

### CR-008: Preview token is bound to slug at verification, but slug changes do **not** invalidate already-issued tokens (CRITICAL)
**File:** `backend/app/Domains/Blog/Actions/GeneratePreviewTokenAction.php:15-29`, `backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php:42`, `backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php:35-49`
**Category:** Security
**Description:** Spec: "Preview tokens MUST be cryptographically bound to the post's current slug AND expires_at. Changing a slug invalidates old preview tokens." The generation and verification correctly embed `$slug` in the HMAC payload and `hash_equals`-compare it (lines 42-44 of preview action) — so a token issued for slug `old-slug` will be **rejected** when presented against `new-slug` (good). **However**, the spec intent ("changing a slug invalidates old preview tokens") is that an admin who changes a slug cannot have old preview links floating around for the *same post*. Because the token is bound to the slug string, an old token presented at `/blog/old-slug/preview?token=…` will still pass verification for `old-slug` — but `old-slug` no longer resolves to any post (slug is unique, the post now has `new-slug`), so `BlogPost::where('slug', $slug)->first()` returns null → 404. This is *effectively* safe, but only by accident of slug uniqueness. The more concrete gap: `UpdateBlogPostAction` allows changing the slug with no check that the post is not mid-preview, and does not record the slug change in the audit metadata beyond `'slug' => $post->slug` (the after-state). There is no explicit token-invalidation event. If a future refactor allows slug aliases or redirects from old slugs, the old token becomes valid again. This is a latent security risk, not a current bypass.
**Impact:** Today: old preview links 404 (safe). Tomorrow: any slug-alias/redirect feature re-enables stale tokens. Spec compliance is by accident, not by design.
**Recommendation:** Add an explicit `blog.slug_change` audit event when `$post->slug !== $oldSlug` and document that slug uniqueness is the invariant that enforces token invalidation. Consider storing a `preview_token_version` (or `slug_revision`) column included in the HMAC payload so a slug change mathematically invalidates tokens regardless of uniqueness.

### CR-009: `InvalidateBlogCacheJob` fallback flushes the **entire** cache store (CRITICAL)
**File:** `backend/app/Domains/Blog/Jobs/InvalidateBlogCacheJob.php:32-37`
**Category:** Security / Correctness
**Description:** The job tries `Cache::tags(['blog','blog_list','blog_categories'])->flush()`. If the cache driver doesn't support tags (file, database, array — common in staging/local and even some prod setups), the `catch (\Throwable)` block runs `Cache::flush()` — which wipes **every** cache key in the application: sessions (if file), tour search caches, homepage, rate-limit counters, route cache, config cache, etc. Every blog post save/archive/publish nukes the entire cache. On a file-driver deployment this also deletes session files → mass logouts.
**Impact:** Catastrophic cache thrash / session loss on non-Redis drivers. A single blog edit can log out every user.
**Recommendation:** Never call `Cache::flush()` as a fallback. Use prefixed key deletion (`Cache::forget` per known key pattern) or require Redis/memcached in deployment docs. At minimum, gate the fallback to only forget known `bookly:blog:*` keys via a scan, and re-throw if unsupported so the failure is visible.

---

### WR-001: `GetBlogPostAction` eager-loads `author.authorProfile` but the model only has `authorProfile` (HasOneThrough) and `author` (BelongsTo User) — `User::authorProfile` may not exist (WARNING)
**File:** `backend/app/Domains/Blog/Actions/GetBlogPostAction.php:21,70,85`
**Category:** Bug
**Description:** `->with(['author.authorProfile', …])` assumes `User` has an `authorProfile()` relation. The `AuthorProfile` model defines `user()` (BelongsTo User), but there is no evidence `User` defines the inverse `authorProfile()` (hasOne). The `BlogPost` model *does* define `authorProfile()` as a `HasOneThrough` via User. So the eager-load path `author.authorProfile` will fail unless `User::authorProfile()` exists. The transformer (line 29-31) handles both `author.authorProfile` and the direct `authorProfile` HasOneThrough, so the intent is ambiguous.
**Impact:** `GetBlogPostAction` will throw `BadMethodCallException` on `with(['author.authorProfile'])` if `User::authorProfile()` is undefined. Even if it is defined, the `relatedPosts` query (line 70, 85) also uses `author.authorProfile`.
**Recommendation:** Either add `User::authorProfile(): HasOne` (inverse of `AuthorProfile::user()`) or change eager-loads to `['authorProfile.user', 'category']` (matching `ListBlogPostsAction`). Pick one canonical eager-load path and use it everywhere.

### WR-002: `GetBlogPostPreviewAction` eager-loads non-existent relations and ignores `BlogPostDetailTransformer` (WARNING)
**File:** `backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php:60-64`
**Category:** Bug
**Description:** (Beyond CR-006's static call.) `with(['author', 'categories', 'tours' => fn => …])` — `categories` and `tours` are not defined on `BlogPost` (model has `category` and `relatedTours`). The closure filters `tours.status = published` and eager-loads `partner, location, pricingRules, reviews` — none of these are validated to exist on `Tour` in this phase's scope, and the `orderBy('blog_post_tours.sort_order')` assumes the pivot table name (which does exist). The preview payload therefore omits `related_posts`, `seo` is unset (line 72), and `is_preview` is added — so the preview response shape differs from the public detail shape, but the Next.js preview page (`preview/page.tsx`) reads `post.status`, `post.body`, `post.author`, `post.related_tours` — `status` is not in the transformer output at all.
**Impact:** Preview page renders broken (see also CR-007). Even after CR-006 fix, `post.status` is undefined.
**Recommendation:** Standardize the preview payload to use `BlogPostDetailTransformer::transform()` + an `is_preview`/`status` overlay. Eager-load `relatedTours` (not `tours`) and `category`.

### WR-003: `UpdateBlogPostAction` publishing logic sets `published_at = now()` even when `scheduled_at` is future (WARNING)
**File:** `backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php:65-68`
**Category:** Bug
**Description:** `if (empty($data['published_at']) && (! $post || ! $post->published_at)) { $data['published_at'] = Carbon::now(); }` runs whenever `status === 'published'`, regardless of `scheduled_at`. A post saved as `published` with `scheduled_at = +2 days` gets `published_at = now()`. `scopePublished` treats `scheduled_at <= now()` as the visibility gate, so the post is still hidden until the scheduled time — but `published_at` is wrong in the payload, sitemap, and SEO `datePublished`. This couples with CR-004.
**Impact:** Incorrect `published_at`/`datePublished` in structured data and API; SEO datePublished lies about the actual publish date.
**Recommendation:** Only set `published_at = now()` when `scheduled_at` is null or past. When scheduled, leave `published_at` null and let `PublishScheduledBlogPostJob` set it.

### WR-004: `UpdateBlogPostAction` does not validate `status` transitions or allow `archived` without prior publish (WARNING)
**File:** `backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php:52-80`
**Category:** Quality / Bug
**Description:** `$targetStatus` is taken from `$data['status']` with no enum validation (any string can be fillable since `status` is in `$fillable`). The actionType branch (lines 72-80) treats `archived` as a valid target even on a draft that was never published — but `GetBlogPostAction` returns 404 for archived-without-published_at, so a draft→archived transition silently becomes a 404. There is no guard preventing `draft → archived` directly. Also no guard against demoting `published → draft` (which would hide the post but leave `published_at` set, causing inconsistent state).
**Impact:** Unenforced state machine; archived-without-publish produces 404 instead of a validation error; draft↔published↔archived transitions are ungoverned.
**Recommendation:** Validate `status` against `[draft, published, archived]` in the action, and enforce: `archived` requires non-null `published_at`; `published → draft` clears `published_at` and `scheduled_at` and logs `blog.unpublish`.

### WR-005: `UpdateBlogPostAction` calls `$post->fresh()->toArray()` for audit "after" state — `toArray()` on JSONB casts returns arrays, inflating audit rows (WARNING)
**File:** `backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php:115`
**Category:** Quality
**Description:** `$post->fresh()->toArray()` serializes the whole model including the full JSONB `title`/`body`/`excerpt`/`meta_description` for all three locales. For a long-form article this is hundreds of KB per audit row, stored on every save (including minor edits). The `before` state is `$post->toArray()` (pre-save, also huge). The audit log will balloon.
**Impact:** Audit table bloat; governance-audit reads become slow.
**Recommendation:** Store a diff or only the changed scalar fields (`status`, `slug`, `published_at`, `scheduled_at`, `is_featured`) plus a content hash, not full before/after arrays.

### WR-006: `GetBlogCategoryAction` and `ListBlogPostsAction` cache keys omit the locale for category action and ignore `is_active` filter on category (WARNING)
**File:** `backend/app/Domains/Blog/Actions/GetBlogCategoryAction.php:40-55`, `backend/app/Domains/Blog/Actions/ListBlogPostsAction.php:53-65`
**Category:** Bug
**Description:** `GetBlogCategoryAction` fetches `BlogCategory::where('slug', $slug)->first()` with **no** `is_active` check — an inactive category's page is served and cached. `ListBlogPostsAction` filtering by `categorySlug` also doesn't check `is_active`. The category cache key includes `{locale}` (good) but the list cache key includes `{locale}` too (good) — however neither cache is invalidated when a *category* is edited (only `InvalidateBlogCacheJob` flush, which is broad). More importantly, the cache TTL is 300s but the `InvalidateBlogCacheJob` flushes by tag — if the driver lacks tags (see CR-009) the keys are never invalidated except by the destructive `Cache::flush()`.
**Impact:** Inactive categories are publicly reachable; cache staleness on non-tag drivers.
**Recommendation:** Add `->where('is_active', true)` to category fetches. Confirm tag support or move to explicit key forget.

### WR-007: `SitemapController` uses `$locales[0]` as the canonical `<loc>` for every URL — wrong for non-default locales (WARNING)
**File:** `backend/app/Domains/Search/Controllers/Public/SitemapController.php:47,55,70,85,97,108,122`
**Category:** Bug / SEO
**Description:** Every `<loc>` is built with `$locales[0]` (i.e. `en`), with `xhtml:link` alternates for each locale. This means the sitemap only declares the English URL as the primary locator for every tour/blog/category, even though the site serves `es` and `it` versions. Google will index the `en` URL only from the sitemap; the `es`/`it` URLs are only discoverable via hreflang alternates (which is acceptable per Google's spec, but the `<loc>` should ideally be the canonical/default URL — this is defensible). The bigger issue: there is **one** sitemap for all locales (no per-locale sitemap index), and the blog index URL is hardcoded to `/blog` (no trailing slash) while the Next.js app may use a different convention. Also, `published_at`/`lastmod` is not emitted for blog posts (spec implies freshness signals).
**Impact:** Suboptimal but not broken SEO. Missing `lastmod` for blog posts.
**Recommendation:** Add `<lastmod>{published_at}</lastmod>` for blog posts. Decide explicitly whether `en` is the canonical `<loc>` (document it) or whether per-locale sitemaps should be generated.

### WR-008: `BlogDetail.tsx` renders `post.body` via `dangerouslySetInnerHTML` with no sanitization (WARNING)
**File:** `frontend/src/components/blog/BlogDetail.tsx:86-89`
**Category:** Security
**Description:** `dangerouslySetInnerHTML={{ __html: post.body }}` — `body` is stored as RichEditor HTML from Filament. Filament's RichEditor produces a constrained HTML subset, but the value is also writable via `UpdateBlogPostAction` from any `manage_blog` admin (and the JSONB column accepts arbitrary content from the action's `fill()`). If an admin account is compromised or a stored-XSS payload slips through (e.g. via a future API client), the script executes in the public site context. The preview endpoint serves the same `body` to unauthenticated preview viewers.
**Impact:** Stored XSS risk if admin input is not constrained; trust boundary between CMS and public site is implicit.
**Recommendation:** Sanitize `body` server-side before storage (e.g. `purify` / `mews/purifier`) or client-side before injection (DOMPurify). At minimum, document that `manage_blog` is a privileged trust boundary and add a server-side HTML allowlist filter in `UpdateBlogPostAction`.

### WR-009: `blog/[slug]/page.tsx` catches errors with `catch (e: any)` and uses `e?.status` — but `NotFoundError`/`GoneError`/`RateLimitError` are typed classes; the `any` + optional chain hides instanceof mismatches (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx:63-74`
**Category:** Quality
**Description:** The code checks `e instanceof GoneError || e?.status === 410` — the `||` is redundant if `GoneError` always sets `status=410` (it does, per `client.ts:143`). Same for `NotFoundError`/404 and `RateLimitError`/429. The `any` cast and optional chaining are a smell. Worse: `e?.retryAfter || 10` on line 68 — `RateLimitError.retryAfter` is a number; if the server sends `Retry-After: 0`, `0 || 10` yields 10 (wrong). And the `throw e` on line 73 re-throws into Next.js's default error boundary, which is fine, but the `generateMetadata` catch (lines 41-52) only handles `GoneError` and a generic fallback — a 429 during metadata generation returns the "Article Not Found" title, mismatching the page body's 429 handling.
**Impact:** Mismatched metadata/body for 429; `retryAfter=0` bug; redundant instanceof checks.
**Recommendation:** Drop the `|| e?.status === …` redundancy, fix `retryAfter ?? 10` (nullish coalesce, not `||`), and handle 429 in `generateMetadata` too.

### WR-010: `blog/page.tsx` swallows all non-429 errors into an empty `blogData` with `meta.total = 0` — masks 404/500 as "no articles" (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/page.tsx:101-116`
**Category:** Quality
**Description:** On any error that isn't 429, the page sets `blogData = { data: [], meta: { total: 0, … } }` and renders the "No articles found" `EmptyState`. A 500 server error, a 404 (impossible for the index but possible if the route misroutes), or a network failure all look identical to "we have no articles" — misleading to users and to ops (no error captured). The category page (`category/[slug]/page.tsx:88-90`) does the opposite: `catch { notFound(); }` — a 500 becomes a 404 page, also wrong.
**Impact:** Silent failure mode; users see "no articles" during outages; monitoring blind spot.
**Recommendation:** Distinguish 429 (rate-limited UI), 404 (notFound), and 5xx/network (render an error boundary or `BlogUnavailable status=500`). Capture the error to telemetry.

### WR-011: `blog/category/[slug]/page.tsx` destructures `{ category, posts, meta }` from `categoryResponse` but the API client returns `BlogCategoryResponse` whose shape is `{ data: { category, posts, meta } }` (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/category/[slug]/page.tsx:33,34,84,92`, `frontend/src/lib/api/types.ts:196-209`
**Category:** Bug
**Description:** `getBlogCategory` returns `BlogCategoryResponse` = `{ data: { category: …, posts: […], meta: {…} } }`. The page does `const categoryData = await getBlogCategory(slug, activeLocale, {…})` (line 33) then `const category = categoryData.category` (line 34) — but `categoryData.category` is undefined; it's `categoryData.data.category`. Same in the page body (line 92: `const { category, posts, meta } = categoryResponse;` — should be `categoryResponse.data`). This will crash with `Cannot read properties of undefined (reading 'name')` on line 35.
**Impact:** Every category page 500s.
**Recommendation:** Destructure from `categoryResponse.data`: `const { category, posts, meta } = categoryResponse.data;`.

### WR-012: `BlogList` component ignores the `meta` prop for pagination — uses `currentPage`/`lastPage` props that are never passed (WARNING)
**File:** `frontend/src/components/blog/BlogList.tsx:5-32`, `frontend/src/app/[locale]/(public)/blog/page.tsx:181-186`, `frontend/src/app/[locale]/(public)/blog/category/[slug]/page.tsx:154-158`
**Category:** Bug
**Description:** `BlogList` interface declares `currentPage?` and `lastPage?` (not `meta`), but every caller passes `meta={meta}` (line 183 of blog/page.tsx, line 156 of category page). The `meta` prop is not in the interface, so TS should warn (it's `BlogListProps` with no `meta`). The pagination block (line 26) only renders if `currentPage !== undefined && lastPage !== undefined` — which are never passed — so **pagination never renders**. The `BlogPostCard` list shows but users can't navigate beyond page 1.
**Impact:** No pagination UI on blog index or category pages; page 2+ unreachable.
**Recommendation:** Change `BlogListProps` to accept `meta: { current_page: number; last_page: number; … }` and derive `currentPage`/`lastPage` from it, or pass `currentPage={meta.current_page} lastPage={meta.last_page}` from every caller.

### WR-013: `blog/page.tsx` computes `itemListElements` using `post.title` and a positional index, but `ItemListSchema` props are `items: {name, url}[]` + `name` — prop name mismatch (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/page.tsx:136-149`, `frontend/src/components/seo/StructuredData.tsx:77-104`
**Category:** Bug
**Description:** The page builds `itemListElements = posts.map((post, idx) => ({ name, url, position }))` and passes it to `<ItemListSchema name="…" itemListElement={itemListElements} />`. But `ItemListSchemaProps` declares `items: { name; url }[]` and `name: string` — there is **no `itemListElement` prop**. The component reads `items` (undefined) and `name`. Result: the rendered schema has `itemListElement: []` (mapped from `undefined`). Also the page's `position` field is computed but the component re-derives `position: i+1` from `items` index — so even if wired correctly, the page's `(pageNum-1)*12 + idx + 1` continuous-positioning is lost.
**Impact:** ItemList structured data is empty/malformed on the blog index — SEO rich-result eligibility lost.
**Recommendation:** Rename the page's variable to `items={posts.map(p => ({ name: p.title, url: … }))}` and pass `name="Bookly Travel Blog Articles"`. If continuous positioning matters, extend `ItemListSchemaProps` to accept an optional `position` per item.

### WR-014: `BreadcrumbListSchema` is called with two different prop shapes — `items` (page.tsx) vs `itemListElement` (category page) (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx:106`, `frontend/src/app/[locale]/(public)/blog/category/[slug]/page.tsx:105`, `frontend/src/components/seo/StructuredData.tsx:174-196`
**Category:** Bug
**Description:** `BreadcrumbListSchema` declares `items: { name: string; url: string }[]`. The blog detail page passes `items={breadcrumbs}` where `breadcrumbs = [{ name, url }]` — correct. The category page passes `itemListElement={breadcrumbItems}` where `breadcrumbItems = [{ name, item }]` — **wrong prop name** (`itemListElement` vs `items`) and **wrong field name** (`item` vs `url`). The component ignores `itemListElement` and renders an empty breadcrumb list.
**Impact:** Category page has empty BreadcrumbList structured data.
**Recommendation:** Standardize on `items={[{ name, url }]}` at both call sites.

### WR-015: `BlogFeaturedHero.tsx`, `BlogDetail.tsx` are not `'use client'` but use no hooks — however `BlogFeaturedHero` reads `post.cover_image`, `post.author.name`, `post.primary_category`, `post.reading_time_minutes` — all wrong field names (WARNING)
**File:** `frontend/src/components/blog/BlogFeaturedHero.tsx:16,40,62,65,76,78,79`
**Category:** Bug
**Description:** (Field-name subset of CR-007, called out separately because this component is a pure presentational server-capable component with multiple wrong fields.) `post.cover_image` (should be `cover_image_url`), `post.primary_category` (should be `category`), `post.author.name` (should be `author.display_name`), `post.reading_time_minutes` (should be `reading_time`). Also the component has no `'use client'` directive and uses no client hooks — fine as a server component — but `BlogCard.tsx` and `BlogDetail.tsx` **do** mark `'use client'` while `BlogFeaturedHero` does not, an inconsistency.
**Impact:** Featured hero renders broken images, missing category badge, "undefined min read", and empty author name.
**Recommendation:** Fix all four field names; decide on a consistent client/server component boundary.

### WR-016: `preview/page.tsx` imports four components (`BlogArticleHero`, `BlogArticleBody`, `BlogAuthorBio`, `BlogRelatedTours`) that do not exist in the repo (WARNING)
**File:** `frontend/src/app/[locale]/(public)/blog/[slug]/preview/page.tsx:4-8`, `frontend/src/components/blog/BlogDetailSkeleton.tsx:1-2`
**Category:** Bug
**Description:** The preview page imports `BlogArticleHero`, `BlogArticleBody`, `BlogAuthorBio`, `BlogRelatedTours` from `@/components/blog/…`. None of these files exist (the repo has `BlogFeaturedHero`, `BlogDetail`, `AuthorByline`, `RelatedTours`). `BlogDetailSkeleton` imports `BlogArticleHeroSkeleton` and `BlogArticleBodySkeleton` which also don't exist. These are unresolved module imports — the build will fail.
**Impact:** Next.js build fails; preview page and detail skeleton are broken.
**Recommendation:** Either create the missing components or rewrite the preview page + skeleton to use the existing `BlogDetail`, `AuthorByline`, `RelatedTours`, `BlogDetailSkeleton` components.

---

### IN-001: `AdminAuthorizationService::FLAGS` includes `manage_cms` and `manage_blog` — `manage_blog` is used by policies, `manage_cms` is unused in this phase (INFO)
**File:** `backend/app/Domains/Admin/Services/AdminAuthorizationService.php:30,31`
**Category:** Quality
**Description:** Both flags exist; blog policies use `manage_blog`. `manage_cms` is presumably for static pages (Spec 013). No issue, just confirming the right flag is used.
**Recommendation:** None — correct.

### IN-002: `BlogPost::readingTime()` uses `str_word_count` on `strip_tags($body)` — English-centric word counting (INFO)
**File:** `backend/app/Domains/Blog/Models/BlogPost.php:128-134`
**Category:** Quality
**Description:** `str_word_count` counts whitespace-separated tokens; for Spanish/Italian this is roughly correct, but CJK or languages without spaces would miscount. The 200 wpm constant is a magic number. Spec says "whole minutes from body word count, minimum 1" — the `max(1, (int) ceil(...))` is correct.
**Recommendation:** Extract `200` to a constant. Document the locale assumption. Consider `mb_substr_count` for non-space-delimited locales if future scope expands.

### IN-003: `BlogPost::contentFor()` falls back to "first filled value" across all locales — may serve Italian content to an English requestor if EN and ES are empty (INFO)
**File:** `backend/app/Domains/Blog/Models/BlogPost.php:110-126`, `backend/app/Domains/Blog/Models/AuthorProfile.php:31-47`
**Category:** Quality
**Description:** The fallback chain is: requested locale → `en` → "first filled value in any locale". The spec says "if requested locale content missing, serve EN with translation_warning: 'partial_translation'". The third tier (first-any-locale) is not in the spec and could serve Italian to an English reader with no `translation_warning` (because `hasLocaleContent` only checks the requested locale, not EN). If EN is also missing, `translationWarning` is set (since `locale !== 'en'` is false for EN requestors), but for an EN requestor with only IT content, the third tier serves IT with **no** warning.
**Impact:** Silent wrong-locale serving for EN requestors when EN is empty.
**Recommendation:** Either remove the third-tier fallback (return null if requested + EN both empty) or extend `hasLocaleContent` to flag a warning whenever the served locale differs from requested.

### IN-004: `GeneratePreviewTokenAction` falls back to `config('app.key')` when `app.preview_key` is absent (INFO)
**File:** `backend/app/Domains/Blog/Actions/GeneratePreviewTokenAction.php:18`
**Category:** Security
**Description:** Reusing `APP_KEY` for HMAC is acceptable as a fallback but means a `APP_KEY` rotation invalidates all outstanding preview tokens (probably fine — they're 30-min TTL). It also means any code path that leaks `APP_KEY` compromises preview token integrity. Prefer a dedicated secret.
**Recommendation:** Add `preview_key` to `config/app.php` and document that it should be a dedicated secret in production. Fail loudly (or log) if neither is set.

### IN-005: `BlogPreviewController::show` reads token from query string — token may appear in server access logs and browser history (INFO)
**File:** `backend/app/Domains/Blog/Controllers/Public/BlogPreviewController.php:17`
**Category:** Security
**Description:** `?token=…` in the URL is logged by nginx/CDN and stored in browser history. For a 30-min preview token this is low-risk but not ideal.
**Recommendation:** Consider accepting the token via a header or POST body for the API path; the Next.js preview page can fetch via POST. Document the tradeoff.

### IN-006: `ListBlogPostsRequest` allows `per_page` up to 100 but `ListBlogPostsAction` clamps to 50 (INFO)
**File:** `backend/app/Domains/Blog/Requests/Public/ListBlogPostsRequest.php:14`, `backend/app/Domains/Blog/Actions/ListBlogPostsAction.php:37`
**Category:** Quality
**Description:** The form request validates `per_page` ≤ 100, but the action clamps to `min(50, …)`. A client requesting `per_page=100` gets 50 with no indication. Inconsistent contract.
**Recommendation:** Align the validation max with the action clamp (both 50, or both 100).

### IN-007: `BlogUnavailable.tsx` hardcodes English copy ("Article Removed", "Too Many Requests", "Retry Now") instead of using `next-intl` (INFO)
**File:** `frontend/src/components/blog/BlogUnavailable.tsx:57,59,67,68,72,73,92,93,99-101,103,117,124,132`
**Category:** Quality
**Description:** The component imports `useTranslations` (line 5) but never calls it — all strings are inline English literals. The `frontend/AGENTS.md` requires `next-intl` for user-facing strings.
**Recommendation:** Replace literals with `t('articleRemoved')`, `t('retryNow')`, etc., and add the keys to `messages/{en,es,it}.json` under `blog`.

### IN-008: `BlogFeaturedHero.tsx` has no `'use client'` directive but `BlogCard.tsx` does — both are pure presentational (INFO)
**File:** `frontend/src/components/blog/BlogFeaturedHero.tsx:1`, `frontend/src/components/blog/BlogCard.tsx:1`
**Category:** Quality
**Description:** `BlogCard` marks `'use client'` (uses `useTranslations`), `BlogFeaturedHero` does not (uses no hooks). `BlogDetail` marks `'use client'` (uses `useTranslations`). The boundary is inconsistent but not broken — `BlogFeaturedHero` could be a server component, which is fine.
**Recommendation:** If `BlogFeaturedHero` should be i18n-aware (it has hardcoded "Featured Story", "Read Article", "min read"), add `useTranslations` + `'use client'` or move the strings to the server-rendered page.

## File-by-File Notes

### Backend — Models / Migrations
- `BlogPost.php` — Missing `categories()`, `primaryCategory()`, `getTranslation()` (CR-001, CR-002). `contentFor` third-tier fallback (IN-003). `readingTime` magic number (IN-002). `relatedTours` vs `tours` naming mismatch.
- `BlogCategory.php` — Clean. `posts()`/`publishedPosts()`/`scopeActive` correct.
- `AuthorProfile.php` — Clean. Same `contentFor` third-tier note as BlogPost.
- Migrations (4 files) — Missing `blog_category_post` pivot migration (CR-001) and `meta_title` column on `blog_posts` (CR-002). Existing migrations are otherwise sound (foreign keys, indexes, JSONB).
- `AdminAuthorizationService.php` — Clean. Correct flag inventory.

### Backend — Actions
- `GeneratePreviewTokenAction.php` — HMAC + base64 correct; `app.key` fallback (IN-004). Slug bound (CR-008). No constant-time on base64 decode (acceptable).
- `GetBlogPostPreviewAction.php` — Static method call (CR-006), bad eager-load (WR-002), fragile expiry parse (CR-003), locale validation good.
- `GetBlogPostAction.php` — 410/404 gating logic correct and well-commented. Eager-load `author.authorProfile` likely broken (WR-001). Related-posts logic (same-category first, exclude self, backfill to 3) is **correct** per spec.
- `GetBlogCategoryAction.php` — Uses non-existent `categories` relation (CR-001); no `is_active` filter (WR-006).
- `ListBlogPostsAction.php` — Uses non-existent `categories` relation (CR-001); `whereRaw('1 = 0')` for missing category is a reasonable empty-result guard.
- `UpdateBlogPostAction.php` — Calls `categories()->sync` (CR-001); `published_at = now()` for scheduled posts (WR-003, CR-004); no status-transition validation (WR-004); audit before/after too large (WR-005); otherwise transaction + audit + cache invalidation + sitemap dispatch wiring is correct.

### Backend — Controllers / Routes / Jobs
- `BlogPostController.php`, `BlogCategoryController.php`, `BlogPreviewController.php` — Thin, correct. Token-in-query (IN-005).
- `InvalidateBlogCacheJob.php` — `Cache::flush()` fallback (CR-009).
- `PublishScheduledBlogPostJob.php` — No state transition (CR-004).
- `RegenerateSitemapJob.php` — Forgets two cache keys; fine. Note `sitemap.xml` key is forgotten but `SitemapController::CACHE_KEY` is `bookly:sitemap:xml` — the second `forget('sitemap.xml')` is dead.
- `SitemapController.php` — `$locales[0]` canonicalization (WR-007); XML escaping correct (`htmlspecialchars`); chunkById streaming correct; blog posts included via `published()` scope (drafts/archived excluded — good).
- `routes/api/public.php` — Blog routes registered; `{slug}/preview` is before `{slug}` (good ordering); rate limiters `throttle:blog`/`blog_detail` registered in `AppServiceProvider`. Clean.

### Backend — Policies / Requests / Transformers / Providers
- `BlogPostPolicy.php`, `BlogCategoryPolicy.php` — All methods gate on `manage_blog`. Correct.
- `ListBlogPostsRequest.php`, `ShowBlogPostRequest.php` — `per_page` max mismatch (IN-006). `ShowBlogPostRequest` inherits locale validation — good.
- `BlogPostDetailTransformer.php` — Reads `meta_title` (CR-002); otherwise SEO/hreflang/author/category shape is reasonable. `relatedTours` filter+take(6) correct. `relatedPosts` take(3) correct.
- `BlogPostTransformer.php` — Card shape correct; field names (`cover_image_url`, `display_name`, `reading_time`) match the TS `BlogPostCard` type — **note this contradicts the Next.js pages which use wrong names (CR-007)**.
- `AppServiceProvider.php` — Policy + morph-map registration for blog models correct. Rate limiters registered. Clean.

### Backend — Filament
- `BlogPostResource.php` — Wrong `Tour` import (CR-005); `categories` relationship field (CR-001); preview action generates URL with `url("/en/blog/…")` (backend host) while `EditBlogPost` uses `config('app.frontend_url')` — inconsistent preview URL bases. Form schema for localized fields as `title_en/_es/_it` is a reasonable pattern but requires the create/edit handlers to reassemble JSONB (which they do).
- `CreateBlogPost.php`, `EditBlogPost.php` — Reassemble JSONB correctly; call `getTranslation` which doesn't exist (CR-002); call `tours()` which should be `relatedTours()` (CR-001).
- `BlogCategoryResource.php` and its 3 pages — Clean. No `is_active` form field issue (it has a Toggle). No policy gate override (Filament uses the registered policy).
- `ViewBlogPost.php` — Clean.

### Frontend — Pages
- `blog/[slug]/page.tsx` — Field-name mismatches (CR-007); error handling smell (WR-009); metadata/body 429 mismatch (WR-009).
- `blog/[slug]/preview/page.tsx` — Imports non-existent components (WR-016); reads `post.status` not in transformer output (WR-002).
- `blog/page.tsx` — Swallows errors into empty state (WR-010); `ItemListSchema` prop mismatch (WR-013); `BlogList` meta prop mismatch (WR-012).
- `blog/category/[slug]/page.tsx` — Destructures wrong level of response (WR-011); `BreadcrumbListSchema` prop mismatch (WR-014); `catch { notFound() }` masks 500s (WR-010).
- `blog/loading.tsx`, `blog/[slug]/loading.tsx`, `blog/category/[slug]/loading.tsx` — Reference `BlogDetailSkeleton` which imports non-existent sub-skeletons (WR-016).

### Frontend — Components
- `BlogDetail.tsx` — `dangerouslySetInnerHTML` (WR-008); otherwise correct field names (`cover_image_url`, `author.display_name` via `AuthorByline`, `reading_time`).
- `BlogCard.tsx` — Clean. Field names correct. `'use client'` + `useTranslations` correct.
- `AuthorByline.tsx` — Clean. Correct `display_name`/`avatar_url`/`bio` usage.
- `BlogFeaturedHero.tsx` — Wrong field names (WR-015); no `'use client'` (IN-008).
- `BlogList.tsx` — `meta` prop not in interface; pagination never renders (WR-012).
- `BlogListSkeleton.tsx` — Clean.
- `BlogDetailSkeleton.tsx` — Imports non-existent sub-skeletons (WR-016).
- `BlogUnavailable.tsx` — Hardcoded English strings (IN-007); countdown logic correct.
- `RelatedPosts.tsx`, `RelatedTours.tsx` — Clean. Guard against empty arrays correct.
- `Pagination.tsx` — Clean. Uses `useSearchParams` + `usePathname` correctly; preserves query string. (Note: this is a shared search component, not new to this phase.)
- `StructuredData.tsx` — `ItemListSchema` and `BreadcrumbListSchema` prop interfaces don't match callers (WR-013, WR-014). `BlogPostingSchema` is correct and well-shaped. `TouristTripSchema` has a likely bug (`duration: PT{minutes}M` should probably be ISO 8601 `PT{minutes}M` is fine for minutes, but `tour.duration.minutes` may be 0 → `PT0M` is valid) — out of scope for this phase.

### Frontend — lib
- `blog.ts` — `BlogArticleDetailResponse` undefined (CR-007); `getBlogPost` sends `preview_token` query param but the backend `BlogPreviewController` expects `token` (the public show endpoint doesn't accept `preview_token` at all — the param is silently ignored). `getBlogPosts`/`getBlogCategory` use `next: { revalidate: 300 }` which matches the backend cache TTL — good.
- `types.ts` — `BlogPostCard`/`BlogPostDetail`/`BlogAuthorSummary`/`BlogCategorySummary` shapes match the backend transformers **for the fields they define** — the mismatches are in the pages reading non-existent fields, not in the types. `BlogCategoryResponse` nesting is correct (WR-011 is a caller bug). Missing `BlogArticleDetailResponse` (CR-007).

### Files with no issues found
- `backend/app/Domains/Admin/Services/AdminAuthorizationService.php`
- `backend/app/Domains/Blog/Models/BlogCategory.php`
- `backend/app/Domains/Blog/Models/AuthorProfile.php` (minor IN-003)
- `backend/app/Domains/Blog/Policies/BlogPostPolicy.php`
- `backend/app/Domains/Blog/Policies/BlogCategoryPolicy.php`
- `backend/app/Domains/Blog/Requests/Public/ShowBlogPostRequest.php`
- `backend/app/Filament/Resources/BlogCategoryResource.php`
- `backend/app/Filament/Resources/BlogCategoryResource/Pages/CreateBlogCategory.php`
- `backend/app/Filament/Resources/BlogCategoryResource/Pages/EditBlogCategory.php`
- `backend/app/Filament/Resources/BlogCategoryResource/Pages/ListBlogCategories.php`
- `backend/app/Filament/Resources/BlogPostResource/Pages/ListBlogPosts.php`
- `backend/app/Filament/Resources/BlogPostResource/Pages/ViewBlogPost.php`
- `backend/database/migrations/2026_08_22_100001_create_blog_categories_table.php`
- `backend/database/migrations/2026_08_22_100003_create_author_profiles_table.php`
- `backend/database/migrations/2026_08_22_100004_create_blog_post_tours_table.php`
- `frontend/src/components/blog/AuthorByline.tsx`
- `frontend/src/components/blog/BlogCard.tsx`
- `frontend/src/components/blog/BlogListSkeleton.tsx`
- `frontend/src/components/blog/RelatedPosts.tsx`
- `frontend/src/components/blog/RelatedTours.tsx`
- `frontend/src/components/search/Pagination.tsx`

---

_Reviewed: 2026-08-22_
_Reviewer: gsd-code-reviewer agent_
_Depth: standard_