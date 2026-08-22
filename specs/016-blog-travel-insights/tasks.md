# Tasks: Blog and Travel Insights

**Feature**: 016-blog-travel-insights | **Date**: 2026-08-22 | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

## Summary

Tasks are organized by user story (P1 → P2 → P3) enabling independent implementation and testing.
Each phase produces a complete, independently testable increment. Shared infrastructure is in
Phase 1 (Setup) and Phase 2 (Foundational). Cross-cutting concerns are in the final Polish phase.

Tests are included per ADR-0021 (backend Pest) and ADR-0022 (frontend Playwright + Jest + a11y).

---

## Phase 1: Setup

- [X] T001 Create blog database migrations for blog_categories table in backend/database/migrations/2026_08_22_100001_create_blog_categories_table.php
- [X] T002 Create blog database migrations for blog_posts table in backend/database/migrations/2026_08_22_100002_create_blog_posts_table.php
- [X] T003 [P] Create blog database migrations for author_profiles table in backend/database/migrations/2026_08_22_100003_create_author_profiles_table.php
- [X] T004 [P] Create blog database migrations for blog_post_tours pivot table in backend/database/migrations/2026_08_22_100004_create_blog_post_tours_table.php
- [X] T005 Add 'manage_blog' to AdminAuthorizationService::FLAGS array in backend/app/Domains/Admin/Services/AdminAuthorizationService.php
- [X] T006 Add PREVIEW_KEY to backend/.env and backend/config/app.php for HMAC token signing
- [X] T007 [P] Add 'blog' and 'blog_detail' rate limiters (120/min per IP) to AppServiceProvider::boot() in backend/app/Providers/AppServiceProvider.php
- [X] T008 Add 'blog' namespace with all blog i18n keys to frontend/messages/en.json, frontend/messages/es.json, and frontend/messages/it.json

---

## Phase 2: Foundational

*Blocking prerequisites — MUST complete before user stories.*

- [X] T009 Create BlogCategory model with fillable, casts, relationships (posts, publishedPosts) in backend/app/Domains/Blog/Models/BlogCategory.php
- [X] T010 Create BlogPost model with LOCALES constant, status constants, fillable, JSONB casts (title/body/excerpt/meta_description), scopes (published, featured), contentFor() localized accessor, and relationships (author, category, relatedTours) in backend/app/Domains/Blog/Models/BlogPost.php
- [X] T011 [P] Create AuthorProfile model with fillable, JSONB casts (display_name/bio), user relationship, and contentFor() localized accessor in backend/app/Domains/Blog/Models/AuthorProfile.php
- [X] T012 Register Gate::policy(BlogPost::class, BlogPostPolicy::class) and Gate::policy(BlogCategory::class, BlogCategoryPolicy::class) plus morph map aliases ('blog_post', 'blog_category') in backend/app/Providers/AppServiceProvider.php
- [X] T013 [P] Create BlogPostPolicy delegating all methods to AdminAuthorizationService::can($user, 'manage_blog') in backend/app/Domains/Blog/Policies/BlogPostPolicy.php
- [X] T014 [P] Create BlogCategoryPolicy delegating all methods to AdminAuthorizationService::can($user, 'manage_blog') in backend/app/Domains/Blog/Policies/BlogCategoryPolicy.php
- [X] T015 [P] Create ListBlogPostsRequest extending LocaleRequest adding category (nullable string), page (nullable integer), per_page (nullable integer min:1) validation rules in backend/app/Domains/Blog/Requests/Public/ListBlogPostsRequest.php
- [X] T016 [P] Create ShowBlogPostRequest extending LocaleRequest in backend/app/Domains/Blog/Requests/Public/ShowBlogPostRequest.php
- [X] T017 [P] Create BlogPostTransformer transforming BlogPost to BlogPostCard shape (id, slug, title, excerpt, cover_image_url, cover_image_blur, published_at, is_featured, reading_time, translation_warning, category, author) with localized fallback in backend/app/Domains/Blog/Transformers/BlogPostTransformer.php
- [X] T018 [P] Create BlogPostDetailTransformer transforming BlogPost to full detail shape (all BlogPostCard fields + body, updated_at, seo block, author with bio, related_tours via TourCardTransformer max 6, related_posts max 3 with backfill, reading_time) in backend/app/Domains/Blog/Transformers/BlogPostDetailTransformer.php
- [X] T019 Add makeBlogPost() and makeBlogCategory() Pest helpers to backend/tests/Pest.php mirroring makeSearchableTour() pattern (Model::create without factories)

---

## Phase 3: User Story 1 — Read a Travel Insights Article (P1)

**Goal**: A traveler visits `/{locale}/blog/{slug}` and sees a fully rendered, SEO-crawlable article with related tours and related posts.

**Independent Test**: Publish one article with EN content and one related tour, visit `/en/blog/{slug}`, verify article renders, SEO metadata present, related tour links to `/en/tours/{tour-slug}`.

- [X] T020 [US1] Create GetBlogPostAction handling slug lookup, 404/410 gating (archived+published_at≠null → 410; draft/scheduled-future/archived-never-published/non-existent → 404), translation fallback with translation_warning, seo block building (self-canonical per locale + hreflang map), reading_time computation, related tours (max 6 published via TourCardTransformer), and related posts (max 3 same-category + cross-category backfill) in backend/app/Domains/Blog/Actions/GetBlogPostAction.php
- [X] T021 [US1] Create BlogPostController with show() method calling GetBlogPostAction, applying 'blog_detail' rate limiter, returning BlogPostDetailTransformer response in backend/app/Domains/Blog/Controllers/Public/BlogPostController.php
- [X] T022 [US1] Register GET /api/public/blog/{slug} route with 'blog_detail' rate limiter in backend/routes/api.php
- [X] T023 [P] [US1] Create BlogPostTest with Pest tests: detail 200 for published post, 404 for draft, 404 for scheduled-future, 404 for non-existent, 410 for archived-previously-published, 404 for archived-never-published, 422 for invalid locale, translation_warning for missing locale content, data.seo block present with canonical_url + hreflang, related tours only published (archived omitted), reading_time present in backend/tests/Feature/Blog/BlogPostTest.php
- [X] T024 [P] [US1] Add BlogPost and BlogPostDetail TypeScript types to frontend/src/lib/api/types.ts matching the API contract (BlogPostCard, BlogPostDetail with seo, related_tours, related_posts, reading_time, translation_warning)
- [X] T025 [P] [US1] Create blog API client with getBlogPost(slug, locale) returning BlogPostDetailResponse, uncached (no revalidate), in frontend/src/lib/api/blog.ts
- [X] T026 [US1] Create BlogDetail component rendering title, cover image (next/image + plaiceholder blur), body HTML via dangerouslySetInnerHTML, author byline (name, avatar, bio), published_at date, category badge, reading_time, partial-translation indicator, and robots noindex for preview mode in frontend/src/components/blog/BlogDetail.tsx
- [X] T027 [US1] Create AuthorByline component rendering author display_name, avatar_url, and bio from author_profiles with localized fallback in frontend/src/components/blog/AuthorByline.tsx
- [X] T028 [US1] Create RelatedTours component rendering up to 6 published related tours using existing TourCard component, ordered by sort_order, in frontend/src/components/blog/RelatedTours.tsx
- [X] T029 [US1] Create RelatedPosts component rendering up to 3 related posts (same-category first, cross-category backfill) as BlogCard links, excluding current post, in frontend/src/components/blog/RelatedPosts.tsx
- [X] T030 [US1] Create BlogCard component for list/detail related-posts rendering: title, cover image, excerpt, published_at, category badge, reading_time, linking to /{locale}/blog/{slug} in frontend/src/components/blog/BlogCard.tsx
- [X] T031 [US1] Add BlogPostingSchema and BreadcrumbListSchema exports to frontend/src/components/seo/StructuredData.tsx using the existing dangerouslySetInnerHTML JSON-LD pattern, with props for headline, description, datePublished, dateModified, author (Person), image, inLanguage, publisher (Organization), mainEntityOfPage, and breadcrumb trail (Home > Blog > Category > Post)
- [X] T032 [US1] Create blog detail page (server component) at frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx with generateMetadata (meta title/description, self-canonical per locale, hreflang alternates, openGraph type='article' with publishedTime/modifiedTime/authors/section/images, twitter card='summary_large_image'), notFound() on NotFoundError, GoneError handling for 410, BlogPostingSchema + BreadcrumbListSchema rendering, and BlogDetail component
- [X] T033 [P] [US1] Create Jest unit test for BlogCard component in frontend/src/components/blog/__tests__/BlogCard.test.tsx mocking next/image and next-intl, with inline typed fixture
- [X] T034 [P] [US1] Create Playwright E2E test blog-detail.spec.ts testing article render, related tours link to tour detail, author byline, reading time, 404 for non-existent slug in frontend/tests/e2e/blog/blog-detail.spec.ts
- [X] T035 [P] [US1] Create Playwright E2E test blog-seo.spec.ts testing meta title/description, canonical, hreflang, OG article type, twitter card, BlogPosting JSON-LD, BreadcrumbList JSON-LD in frontend/tests/e2e/blog/blog-seo.spec.ts
- [X] T036 [P] [US1] Create axe-core a11y test blog-detail-a11y.spec.ts using AxeBuilder against /en/blog/{slug} in frontend/tests/e2e/a11y/blog-detail-a11y.spec.ts

---

## Phase 4: User Story 2 — Browse the Blog Listing by Category (P2)

**Goal**: A traveler visits `/{locale}/blog`, sees a featured hero + paginated list, filters by category via shareable URL, and sees an empty state when no posts match.

**Independent Test**: Publish 3 posts across 2 categories (one featured), visit `/en/blog`, verify featured section + paginated list, apply `?category=food-drink`, verify filtered list and shareable URL.

- [X] T037 [US2] Create ListBlogPostsAction handling paginated listing (default 12/page, newest-first by published_at DESC), category filter via ?category={slug}, public visibility scope (status=published + scheduled_at<=now), 5-min Redis cache (bookly:blog:list:{locale}:{hash}), and translation fallback per item in backend/app/Domains/Blog/Actions/ListBlogPostsAction.php
- [X] T038 [US2] Create GetBlogCategoryAction handling category lookup by slug (404 if not found), paginated published posts in that category (newest-first), 5-min Redis cache (bookly:blog:category:{slug}:{locale}:{hash}), in backend/app/Domains/Blog/Actions/GetBlogCategoryAction.php
- [X] T039 [US2] Add index() method to BlogPostController calling ListBlogPostsAction with 'blog' rate limiter returning { data, meta } via BlogPostTransformer in backend/app/Domains/Blog/Controllers/Public/BlogPostController.php
- [X] T040 [US2] Create BlogCategoryController with show() method calling GetBlogCategoryAction with 'blog' rate limiter in backend/app/Domains/Blog/Controllers/Public/BlogCategoryController.php
- [X] T041 [US2] Register GET /api/public/blog (list) and GET /api/public/blog/category/{slug} routes with 'blog' rate limiter in backend/routes/api.php
- [X] T042 [P] [US2] Create BlogListTest with Pest tests: listing 200 with pagination meta, category filter returns only that category's posts, featured posts included in list with is_featured=true, empty data array when no posts, 422 for invalid locale, 422 for invalid per_page, newest-first ordering, cache hit on second request in backend/tests/Feature/Blog/BlogListTest.php
- [X] T043 [P] [US2] Create BlogCategoryTest with Pest tests: category 200 with posts, category 404 for non-existent slug, inactive category still reachable by direct URL, paginated posts in category, 422 for invalid locale in backend/tests/Feature/Blog/BlogCategoryTest.php
- [X] T044 [P] [US2] Add getBlogPosts(locale, params) and getBlogCategory(slug, locale, params) functions with revalidate: 300 to frontend/src/lib/api/blog.ts
- [X] T045 [US2] Create BlogList component rendering responsive grid (1-col mobile, 2-col tablet, 3-col desktop) of BlogCard items with pagination, reusing existing Pagination component with blog-specific aria-label in frontend/src/components/blog/BlogList.tsx
- [X] T046 [US2] Create BlogFeaturedHero component rendering the featured post(s) in a hero section above the paginated list with cover image, title, excerpt, and link to detail in frontend/src/components/blog/BlogFeaturedHero.tsx
- [X] T047 [US2] Create blog listing page (server component) at frontend/src/app/[locale]/(public)/blog/page.tsx with generateMetadata (type='website', twitter card='summary'), category filter reflected in URL ?category={slug} (shareable/bookmarkable), featured hero section, paginated BlogList, empty state ("No posts yet" with browse-tours CTA) using EmptyState component, ItemListSchema rendering, and LoadingSkeleton for loading state
- [X] T048 [US2] Create blog category page (server component) at frontend/src/app/[locale]/(public)/blog/category/[slug]/page.tsx with generateMetadata (type='website', twitter card='summary'), category name as page title, paginated BlogList filtered by category, notFound() on NotFoundError, BreadcrumbListSchema, and empty state when no posts in category
- [X] T049 [P] [US2] Create Playwright E2E test blog-list.spec.ts testing pagination, category filter, URL shareability, featured hero section, empty state, and ItemList structured data in frontend/tests/e2e/blog/blog-list.spec.ts
- [X] T050 [P] [US2] Create Playwright E2E test blog-i18n.spec.ts testing locale switch (EN/ES/IT), hreflang links, canonical per locale, and partial-translation indicator in frontend/tests/e2e/blog/blog-i18n.spec.ts
- [X] T051 [P] [US2] Create axe-core a11y test blog-list-a11y.spec.ts using AxeBuilder against /en/blog in frontend/tests/e2e/a11y/blog-list-a11y.spec.ts

---

## Phase 5: User Story 3 — Author and Publish a Blog Post as an Admin (P3)

**Goal**: An admin creates, previews, publishes, and archives blog posts in Filament with audit logging and scheduled publishing.

**Independent Test**: Sign in as admin with manage_blog, create a post with EN content, save as draft, generate preview token, open preview URL, publish, confirm post appears on public listing and sitemap.

- [X] T053 [US3] Create UpdateBlogPostAction handling create, update, publish (status→published + published_at ?? now()), archive (status→archived), slug generation from EN title via Str::slug(), validation (EN title+body required to publish), GovernanceAuditService::log() with blog.publish/blog.update/blog.archive keys in same transaction, cache invalidation (flush list/category keys), sitemap cache flush dispatch, and PublishScheduledBlogPostJob dispatch when scheduled_at is future in backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php
- [X] T054 [US3] Create GeneratePreviewTokenAction issuing HMAC token bound to slug + expires_at (30-min TTL) using Hash::hmac('sha256', "{$slug}|{$expiresAt}", config('app.preview_key')) in backend/app/Domains/Blog/Actions/GeneratePreviewTokenAction.php
- [X] T055 [US3] Create GetBlogPostPreviewAction verifying HMAC token (decode, recompute hmac with requested slug, hash_equals comparison, expires_at check, slug-rebinding prevention), returning post regardless of status with is_preview=true flag and no seo block in backend/app/Domains/Blog/Actions/GetBlogPostPreviewAction.php
- [X] T056 [US3] Create BlogPreviewController with show() method calling GetBlogPostPreviewAction with 'blog_detail' rate limiter in backend/app/Domains/Blog/Controllers/Public/BlogPreviewController.php
- [X] T057 [US3] Register GET /api/public/blog/{slug}/preview route with 'blog_detail' rate limiter in backend/routes/api.php
- [X] T058 [US3] Create InvalidateBlogCacheJob flushing bookly:blog:list:* and bookly:blog:category:* cache keys for affected locales in backend/app/Domains/Blog/Jobs/InvalidateBlogCacheJob.php
- [X] T059 [P] [US3] Create RegenerateSitemapJob flushing bookly:sitemap:xml cache key in backend/app/Domains/Blog/Jobs/RegenerateSitemapJob.php
- [X] T060 [US3] Create PublishScheduledBlogPostJob re-validating post scheduling state (still published + scheduled_at <= now), then dispatching InvalidateBlogCacheJob and RegenerateSitemapJob, dispatched with delay($post->scheduled_at) from UpdateBlogPostAction in backend/app/Domains/Blog/Jobs/PublishScheduledBlogPostJob.php
- [X] T061 [US3] Create BlogPostResource Filament resource in Content nav group with: slug input (unique validation), status select (draft/published/archived), per-locale RichEditor fields for body (localizedSchema pattern), per-locale TextInput for title/excerpt/meta_description, is_featured toggle, scheduled_at datetime picker, author_id select, blog_category_id select, cover image upload, related tours repeater (multi-select with sort_order), and preview token generation action on Edit page in backend/app/Filament/Resources/BlogPostResource.php
- [X] T062 [P] [US3] Create BlogCategoryResource Filament resource in Content nav group with: name input, slug input (unique validation), description textarea, is_active toggle, display_order input, in backend/app/Filament/Resources/BlogCategoryResource.php
- [X] T063 [P] [US3] Create BlogCategoryResource Pages (List, Create, Edit) in backend/app/Filament/Resources/BlogCategoryResource/Pages/
- [X] T064 [US3] Create BlogPostResource Pages (List, Create, Edit, View) reassembling per-locale flat fields into JSONB arrays and routing through UpdateBlogPostAction in backend/app/Filament/Resources/BlogPostResource/Pages/
- [X] T065 [P] [US3] Create BlogPreviewTest with Pest tests: preview 200 with valid token for draft post, 403 with expired token, 403 with tampered token, 403 with slug-rebinding (valid token for post A used on post B slug), 404 for non-existent slug, 422 for invalid locale, preview returns is_preview=true with no seo block in backend/tests/Feature/Blog/BlogPreviewTest.php
- [X] T066 [P] [US3] Create BlogAuthorizationTest with Pest tests: admin without manage_blog cannot create/edit/publish/archive, admin with manage_blog can, non-admin (traveler/partner) cannot access Filament blog resources, audit log entries created for blog.publish/blog.update/blog.archive with actor/action/target/before/after in backend/tests/Feature/Blog/BlogAuthorizationTest.php
- [X] T067 [US3] Create blog preview page (server component) at frontend/src/app/[locale]/(public)/blog/[slug]/preview/page.tsx with robots { index: false, follow: false }, no sitemap inclusion, fetching via getBlogPostPreview(slug, token, locale), rendering BlogDetail with is_preview banner, and no caching (no revalidate)
- [X] T068 [P] [US3] Add getBlogPostPreview(slug, token, locale) function (uncached, no revalidate) to frontend/src/lib/api/blog.ts
- [X] T069 [US3] Extend existing SitemapController to stream published blog posts via chunkById(500) with hreflang alternates for all 3 locales, blog category URLs, and blog index page URLs, all within the existing Redis 3600s cache + Cache-Control header, in backend/app/Domains/Search/Controllers/Public/SitemapController.php
- [X] T070 [P] [US3] Create BlogSitemapTest with Pest tests: published blog posts in sitemap with hreflang, draft/scheduled-future/archived posts excluded, blog categories in sitemap, blog index page in sitemap, beforeEach Cache::flush() in backend/tests/Feature/Blog/BlogSitemapTest.php

---

## Phase 6: Polish & Cross-Cutting Concerns

- [X] T071 Create BlogUnavailable component mirroring SearchUnavailable for 429 rate-limit fallback on blog listing/detail with role="status", aria-live="polite", countdown auto-retry, and browse-tours fallback link in frontend/src/components/blog/BlogUnavailable.tsx
- [X] T072 [P] Wire RateLimitError (429) handling in blog listing page to render BlogUnavailable with Retry-After in frontend/src/app/[locale]/(public)/blog/page.tsx
- [X] T073 [P] Wire RateLimitError (429) handling in blog detail page to render BlogUnavailable with Retry-After in frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx
- [X] T074 [P] Add 410 GoneError handling in blog detail page to render a branded "content removed" page (distinct from 404 not-found) in frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx
- [X] T075 [P] Add skeleton loading states using LoadingSkeleton component (variant='card' for listing, variant='detail' for detail) to blog pages in frontend/src/app/[locale]/(public)/blog/page.tsx and frontend/src/app/[locale]/(public)/blog/[slug]/page.tsx
- [X] T076 Verify npm run build passes with zero TypeScript errors across all blog components and pages
- [X] T077 [P] Verify npm run lint passes with zero ESLint errors across all blog components and pages
- [X] T078 [P] Verify npm run typecheck (tsc --noEmit) passes with zero errors across all blog types
- [X] T079 Run Lighthouse audit on /en/blog and /en/blog/{slug} confirming Performance >= 90 and Accessibility >= 95
- [X] T080 Verify all three locale message files (en.json, es.json, it.json) have complete and matching blog namespace keys
- [X] T081 [P] Verify visual diff of ST-016-001 (blog listing desktop) and ST-016-002 (blog listing mobile) against Stitch designs at 390px, 780px, 1280px

---

## Dependencies

```text
Phase 1 (Setup)          → no dependencies
Phase 2 (Foundational)   → depends on Phase 1 (migrations must exist before models)
Phase 3 (US1 — Detail)   → depends on Phase 2 (models, transformers, policies, helpers)
Phase 4 (US2 — Listing)  → depends on Phase 2 (models, transformers); independent of Phase 3
Phase 5 (US3 — Admin)    → depends on Phase 2 (models, policies); independent of Phases 3-4
Phase 6 (Polish)         → depends on Phases 3-5 (all pages exist before wiring fallbacks/audits)
```

### Story Completion Order

```text
US1 (P1) and US2 (P2) can proceed in parallel after Phase 2.
US3 (P3) can proceed in parallel with US1 and US2 after Phase 2.
All three stories are independently testable.
```

### MVP Scope

**User Story 1 (P1)** is the MVP. A single published article with EN content, one related tour,
SEO metadata, and structured data proves the blog delivers value. Phases 1 + 2 + 3 produce the MVP.

---

## Parallel Execution Examples

### Within US1 (Phase 3)

```text
T024 (types) + T025 (API client) + T023 (backend tests) + T033 (Jest test) — all [P], different files
T026 (BlogDetail) + T030 (BlogCard) — sequential (BlogCard used by BlogDetail's related posts)
T027 (AuthorByline) + T028 (RelatedTours) + T029 (RelatedPosts) — [P], independent components
T031 (StructuredData) + T032 (page) — sequential (page renders schemas from StructuredData)
T034 (E2E detail) + T035 (E2E seo) + T036 (a11y) — [P], independent test files
```

### Within US2 (Phase 4)

```text
T042 (backend list tests) + T043 (backend category tests) + T044 (frontend API client) — [P]
T045 (BlogList) + T046 (BlogFeaturedHero) — [P], independent components
T049 (E2E list) + T050 (E2E i18n) + T051 (a11y list) — [P], independent test files
```

### Within US3 (Phase 5)

```text
T059 (RegenerateSitemapJob) + T062 (BlogCategoryResource) + T063 (Category Pages) + T065 (preview tests) + T066 (auth tests) + T068 (preview API client) + T070 (sitemap tests) — all [P]
T053 (UpdateBlogPostAction) → T058 (InvalidateBlogCacheJob) — sequential (action dispatches job)
T054 (GeneratePreviewTokenAction) → T055 (GetBlogPostPreviewAction) — sequential (preview verifies what generate produces)
```

---

## Implementation Strategy

1. **MVP First** (Phases 1-3): Deliver US1 — one readable, SEO-crawlable article with related tours.
2. **Discovery** (Phase 4): Add US2 — listing with category filter, featured hero, pagination, empty state.
3. **Authoring** (Phase 5): Add US3 — Filament admin authoring, preview tokens, scheduling, audit, sitemap.
4. **Polish** (Phase 6): Wire rate-limit fallbacks, 410 handling, loading skeletons, visual diff, Lighthouse, i18n completeness.

Each phase produces a deployable, independently testable increment.