# Spec 016 acceptance criteria: plan + constitution + ADR gates

Spec 016 is done when ALL of the following pass:

## From frontend-implementation-plan.md:468-476
- Blog SEO metadata present (Lighthouse SEO audit on `/blog` and `/blog/[slug]`).
- Blog listing pagination works.
- Blog post renders rich content correctly.
- Related tours display and link correctly.
- EN/ES/IT content renders correctly.
- Visual diff of ST-016-001 and ST-016-002 at 390px, 780px, 1280px.

## From the constitution (specs/main/plan.md:32-40)
- API-first: public blog consumes Laravel backend via APIs (no backend-rendered app views).
- Filament admin is the sole ratified exception to API-first (blog authoring in Filament).
- Tours-only discipline does not apply (blog is editorial content, not a tour listing; posts link to tours but are not bookings).

## From Spec 013 governance
- Every blog governance action (publish/update/archive) writes an immutable
  `governance_audit_logs` entry with actor, action (`blog.*`), target, before/after.

## From the ADRs (0001-0022)
- Blog posts authored in Filament `BlogPostResource` (Content nav group), `manage_blog` flag.
- JSONB-localized title/body/excerpt/meta_description keyed by locale (en/es/it).
- Single non-localized `slug` unique, generated via `Str::slug()`, validated `^[a-z0-9-]+$`.
- Statuses draft/published/archived + `scheduled_at` for future publishing.
- EN required to publish; ES/IT optional with EN fallback + `translation_warning: 'partial_translation'`.
- Separate `blog_categories` table; `author_profiles` table (1:1 with users);
  `blog_post_tours` pivot.
- Public API: 4 endpoints under `/api/public/blog` with `?locale=` validation;
  `data.seo` block on detail (meta_title, meta_description, canonical_url, hreflang).
- Self-canonical per locale + hreflang alternates; sitemap extended with blog URLs.
- BlogPosting + BreadcrumbList + ItemList JSON-LD; article OG type + twitter cards on detail.
- Signed preview token (time-limited, post-bound) for admin preview; `robots: noindex` on preview route.
- Caching: Redis 5min list/category, uncached detail, 1hr sitemap; invalidation on publish/archive.
- Errors: 404 (draft/scheduled-future/non-existent/never-published-archived),
  410 (archived-previously-published), 422 (invalid locale/slug/per_page), 429 (rate limit).
- Full Pest backend suite + full Playwright/Jest/a11y frontend suite passing.
- `npm run build` + `npm run lint` + `npm run typecheck` clean (no errors).