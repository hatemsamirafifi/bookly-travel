# Implementation Plan: Blog and Travel Insights

**Branch**: `feature/016-blog-travel-insights` | **Date**: 2026-08-22 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/016-blog-travel-insights/spec.md`

## Summary

Build the editorial blog (Travel Insights) for Bookly — a content-marketing surface authored by
admins in a Filament `BlogPostResource`, persisted in a Laravel DB model with JSONB-localized
columns (mirroring the `StaticPage` CMS precedent), and served to the public Next.js 16 frontend
via a read-only API under `/api/public/blog`. The implementation covers blog posts, categories,
author profiles, related-tour links, preview tokens, SEO (canonical/hreflang/OG/JSON-LD/sitemap),
caching + scheduled-transition invalidation, governance audit, and full Pest + Playwright/Jest/a11y
test suites.

## Technical Context

**Language/Version**: PHP 8.3+ (Laravel 11) backend; TypeScript 5+ (Next.js 16) frontend
**Primary Dependencies**:
- Backend: Laravel 11, Filament 3 (RichEditor for HTML body), Redis (cache + queue), `Str::slug()`
- Frontend: Next.js 16 (App Router), Tailwind CSS 4, next-intl 4, plaiceholder (blur placeholders), `@axe-core/playwright`, Playwright, Jest
**Storage**: PostgreSQL (JSONB-localized columns on `blog_posts` + `author_profiles`; pivot tables `blog_post_tours` + `blog_category_post`; Redis for list/category/sitemap cache + queue)
**Testing**: Pest (backend, mirrors `tests/Feature/Search/` suite); Playwright E2E + Jest unit + axe-core a11y (frontend)
**Target Platform**: Linux server (backend); Web — desktop 1280px+ and mobile 390px responsive (frontend)
**Project Type**: web-application (Laravel API + Filament admin; Next.js SSR/SSG frontend consuming read-only API)
**Performance Goals**: Lighthouse Performance ≥ 90, Accessibility ≥ 95 on public blog pages; published article readable < 2s first-byte to fully-rendered; listing supports 50+ posts with filtering/pagination < 2s
**Constraints**: 3 locales (en/es/it) with EN-required-to-publish + partial-translation fallback; `robots: noindex` on preview; 410 Gone for archived-previously-published; signed HMAC preview tokens bound to slug + expires_at; immutable audit log
**Scale/Scope**: 4 public API endpoints, 2 Filament resources, 3 frontend routes (listing, detail, category), 3 new DB tables + 2 pivots, ~15 backend test files, ~6 frontend test files

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

No `.specify/memory/constitution.md` exists. Gates derived from the platform principles
referenced in ADR-0001 and ADR-0023 (frontend-implementation-plan.md constitution summary):

| Principle | Status | Evidence |
|-----------|--------|----------|
| **API-First** | ✅ PASS | Public blog consumes Laravel backend via `/api/public/blog` read-only APIs; no backend-rendered app views for the public blog |
| **Filament admin exception** | ✅ PASS | Blog authoring in Filament `BlogPostResource` is the sole ratified exception to API-first (per Spec 013 precedent, ADR-0001) |
| **Tours-Only Discipline** | ✅ PASS | Blog is editorial content, not a tour listing; posts link to tours for discovery but are not bookings (ADR-0023) |
| **Admin-Governed Publishing** | ✅ PASS | Only `published` posts with `scheduled_at <= now` appear publicly; `manage_blog` permission flag gates authoring (ADR-0013) |
| **SEO-First** | ✅ PASS | SSR/SSG crawlable HTML, meta/OG/JSON-LD structured data, canonical + hreflang, sitemap inclusion (FR-019–024, ADR-0010/0015/0019/0020) |
| **Security** | ✅ PASS | Signed HMAC preview tokens (bound to slug + expires_at), `manage_blog` authorization, immutable audit log, rate limiting (120/min list+detail, 10/min sitemap), input validation (422) |
| **Testing** | ✅ PASS | Full Pest backend suite mirroring Search tests (ADR-0021); full Playwright + Jest + axe-core a11y frontend suite (ADR-0022) |

**Verdict**: All gates pass. No violations.

## Project Structure

### Documentation (this feature)

```text
specs/016-blog-travel-insights/
├── plan.md              # This file
├── research.md          # Phase 0 — codebase pattern research
├── data-model.md        # Phase 1 — entities, fields, relationships, state transitions
├── quickstart.md        # Phase 1 — dev setup guide
├── contracts/
│   └── blog-api.md      # Phase 1 — 4 public blog API endpoints
└── tasks.md             # Phase 2 — /speckit.tasks output (NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
backend/
├── app/
│   ├── Domains/
│   │   ├── Blog/
│   │   │   ├── Models/
│   │   │   │   ├── BlogPost.php
│   │   │   │   ├── BlogCategory.php
│   │   │   │   └── AuthorProfile.php
│   │   │   ├── Actions/
│   │   │   │   ├── GetBlogPostAction.php          # detail (404/410 gating + seo block)
│   │   │   │   ├── GetBlogPostPreviewAction.php   # preview token verification
│   │   │   │   ├── ListBlogPostsAction.php        # paginated listing + category filter
│   │   │   │   ├── GetBlogCategoryAction.php      # category detail + paginated posts
│   │   │   │   ├── UpdateBlogPostAction.php       # create/update + publish/archive + audit
│   │   │   │   └── GeneratePreviewTokenAction.php # HMAC token issued from Filament
│   │   │   ├── Controllers/
│   │   │   │   ├── Public/
│   │   │   │   │   ├── BlogPostController.php     # GET /api/public/blog, /{slug}
│   │   │   │   │   ├── BlogCategoryController.php # GET /api/public/blog/category/{slug}
│   │   │   │   │   └── BlogPreviewController.php  # GET /api/public/blog/{slug}/preview
│   │   │   ├── Transformers/
│   │   │   │   ├── BlogPostTransformer.php        # list/card shape
│   │   │   │   └── BlogPostDetailTransformer.php  # detail shape (seo, author, related tours, reading_time)
│   │   │   ├── Requests/
│   │   │   │   └── Public/
│   │   │   │       ├── ListBlogPostsRequest.php   # extends LocaleRequest
│   │   │   │       └── ShowBlogPostRequest.php    # extends LocaleRequest
│   │   │   ├── Policies/
│   │   │   │   └── BlogPostPolicy.php             # manage_blog gate
│   │   │   └── Jobs/
│   │   │       ├── InvalidateBlogCacheJob.php       # flush list/category cache on publish/update/archive
│   │   │       ├── RegenerateSitemapJob.php         # dispatch sitemap cache flush (reuses existing pattern)
│   │   │       └── PublishScheduledBlogPostJob.php  # scheduled-transition invalidation at scheduled_at
│   │   └── Search/Controllers/Public/
│   │       └── SitemapController.php              # EXTEND existing — add blog URLs + categories
│   ├── Filament/Resources/
│   │   ├── BlogPostResource.php                   # Content nav group, RichEditor body, manage_blog
│   │   └── BlogCategoryResource.php               # Content nav group
│   └── Domains/Admin/Services/
│       ├── GovernanceAuditService.php             # REUSE — blog.publish/blog.update/blog.archive keys
│       └── AdminAuthorizationService.php          # EXTEND FLAGS — add 'manage_blog'
├── database/migrations/
│   ├── 2026_08_22_100001_create_blog_categories_table.php
│   ├── 2026_08_22_100002_create_blog_posts_table.php
│   ├── 2026_08_22_100003_create_author_profiles_table.php
│   ├── 2026_08_22_100004_create_blog_post_tours_table.php
│   └── 2026_08_22_100005_add_manage_blog_to_admin_permissions.php
└── tests/
    ├── Feature/Blog/
    │   ├── BlogPostTest.php
    │   ├── BlogListTest.php
    │   ├── BlogCategoryTest.php
    │   ├── BlogPreviewTest.php
    │   ├── BlogSitemapTest.php
    │   └── BlogAuthorizationTest.php
    └── Pest.php                                    # EXTEND — add makeBlogPost(), makeBlogCategory()

frontend/
├── src/
│   ├── app/[locale]/(public)/blog/
│   │   ├── page.tsx                               # Blog listing (SSR, revalidate: 300)
│   │   ├── [slug]/page.tsx                        # Blog detail (SSR, uncached)
│   │   ├── [slug]/preview/page.tsx                # Preview (SSR, noindex, never cached)
│   │   └── category/[slug]/page.tsx               # Category-filtered listing (SSR, revalidate: 300)
│   ├── components/
│   │   ├── blog/
│   │   │   ├── BlogCard.tsx                       # List card (title, cover, excerpt, date, category)
│   │   │   ├── BlogList.tsx                       # Grid + featured hero + pagination wrapper
│   │   │   ├── BlogDetail.tsx                     # Article render (body HTML, byline, related tours/posts)
│   │   │   ├── BlogFeaturedHero.tsx               # Featured post hero section
│   │   │   ├── RelatedTours.tsx                   # Reuses TourCard (max 6)
│   │   │   ├── RelatedPosts.tsx                   # Same-category + cross-category backfill (max 3)
│   │   │   ├── AuthorByline.tsx                   # Name, avatar, bio from author_profiles
│   │   │   └── BlogUnavailable.tsx                # 429 fallback (mirrors SearchUnavailable)
│   │   └── seo/
│   │       └── StructuredData.tsx                 # EXTEND — add BlogPostingSchema, BreadcrumbListSchema
│   ├── lib/
│   │   ├── api/
│   │   │   ├── blog.ts                            # getBlogPosts, getBlogPost, getBlogPostPreview, getBlogCategory
│   │   │   └── types.ts                           # EXTEND — BlogPost, BlogPostDetail, BlogCategory, BlogPostListResponse types
│   │   └── i18n/                                  # next-intl (existing)
│   └── messages/
│       ├── en.json                                # EXTEND — add "blog" namespace
│       ├── es.json                                # EXTEND — add "blog" namespace
│       └── it.json                                # EXTEND — add "blog" namespace
└── tests/
    ├── e2e/blog/
    │   ├── blog-list.spec.ts
    │   ├── blog-detail.spec.ts
    │   ├── blog-i18n.spec.ts
    │   └── blog-seo.spec.ts
    ├── e2e/a11y/
    │   ├── blog-list-a11y.spec.ts
    │   └── blog-detail-a11y.spec.ts
    └── (Jest co-located) src/components/blog/__tests__/BlogCard.test.tsx
```

**Structure Decision**: Web application (Option 2). The Laravel backend lives in `backend/` with a
new `Domains/Blog` bounded context mirroring `Domains/Search` and `Domains/Admin`. The Next.js
frontend lives in `frontend/` with new routes under `app/[locale]/(public)/blog/`. Both reuse
existing infrastructure (SitemapController, GovernanceAuditService, AdminAuthorizationService,
StructuredData, TourCard, EmptyState, LoadingSkeleton).

## Complexity Tracking

> No violations to justify. All constitution gates pass.