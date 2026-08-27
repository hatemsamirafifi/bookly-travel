# Feature Specification: Blog and Travel Insights

**Feature Branch**: `016-blog-travel-insights`
**Created**: 2026-08-21
**Status**: Draft
**Input**: User description: "Blog and Travel Insights — editorial blog with categories, authors, localized content, SEO, sitemap, related tours, preview, and Filament CMS authoring"

## Clarifications

### Session 2026-08-22

- Q: How is cache/sitemap invalidation handled when a scheduled post transitions to publicly visible at `scheduled_at` (no explicit admin action)? → A: A queued transition job runs at `scheduled_at` (or a short polling interval) to re-validate the post's scheduling state, invalidate affected list/category caches, and dispatch sitemap regeneration.
- Q: What is the preview token cryptographically bound to (post_id vs slug)? → A: Preview tokens MUST be cryptographically bound to the post's current slug and `expires_at`. The preview endpoint MUST verify the token against the requested `{slug}` before returning preview content. Changing a post's slug invalidates previously issued preview tokens for that post.
- Q: What is the maximum number of related tours surfaced on a blog detail page? → A: The public blog detail page MUST surface at most 6 related published tours, ordered by `sort_order`. Administrators MAY attach more than 6 related tours, but only the first 6 eligible published tours are rendered publicly.
- Q: What happens when fewer than 3 same-category related posts exist for FR-012? → A: Related posts MUST prefer up to 3 published posts from the current post's category, ordered newest-first and excluding the current post. If fewer than 3 eligible same-category posts exist, the remaining slots MUST be backfilled with published posts from other categories, ordered newest-first, until 3 total posts are available.
- Q: Is `reading_time` part of the public blog detail endpoint contract (FR-014)? → A: The public blog detail endpoint MUST include `reading_time` as an estimated reading time in whole minutes, derived from the post body word count, with a minimum value of 1.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Read a Travel Insights Article (Priority: P1)

A prospective traveler browsing the public website encounters the Travel Insights blog.
They open the blog listing, filter by a category (e.g. "Food & Drink"), and click an
article. The article page renders the localized title, cover image, rich body content,
author byline, published date, category badge, related tours they can book, and related
articles in the same category. The page is crawlable: it carries a meta title, meta
description, canonical URL, hreflang alternates, Open Graph article tags, a twitter card,
and `BlogPosting` + `BreadcrumbList` structured data.

**Why this priority**: The blog's primary purpose is content marketing and tour discovery.
Without a readable, SEO-crawlable article page, the blog delivers no value. This story is
the MVP: a single published article, served in one locale, with SEO metadata and related
tours.

**Independent Test**: Can be fully tested by publishing one article with EN content and
one related tour, then visiting `/{locale}/blog/{slug}` and verifying the article renders,
SEO metadata is present, and the related tour links to the tour detail page.

**Acceptance Scenarios**:

1. **Given** a published blog post with EN title and body and one related published tour,
   **When** a traveler visits `/en/blog/{slug}`,
   **Then** the page renders the title, cover image, body, author byline, published date,
   category badge, the related tour card linking to `/en/tours/{tour-slug}`, and
   `BlogPosting` + `BreadcrumbList` JSON-LD structured data.
2. **Given** a published blog post with EN content only,
   **When** a traveler visits `/es/blog/{slug}?locale=es`,
   **Then** the page renders the EN content with a visible partial-translation indicator
   and the API response includes `translation_warning: 'partial_translation'`.
3. **Given** a draft, scheduled-future, or archived-never-published blog post,
   **When** a traveler requests `/en/blog/{slug}`,
   **Then** the page returns a 404 branded not-found page and the post is absent from the
   sitemap.
4. **Given** a previously-published post that has since been archived,
   **When** a traveler requests its slug,
   **Then** the page returns 410 Gone (distinct from a never-published 404).

---

### User Story 2 - Browse the Blog Listing by Category (Priority: P2)

A traveler visits the blog index page. They see a hero/featured section of editorially
curated posts, followed by a paginated reverse-chronological list of all published posts.
They filter by a category and see only posts in that category, with the URL reflecting the
filter so it is shareable/bookmarkable. If no posts match, an empty state appears with a
suggestion to browse tours.

**Why this priority**: Discovery is the entry point to the articles. Without listing and
category filtering, readers cannot find content. This story is independently viable:
listing + category filter works even if the detail page's related-tours feature is absent.

**Independent Test**: Publish 3 posts across 2 categories (one featured), visit
`/{locale}/blog`, verify the featured section + paginated list, apply `?category={slug}`,
verify the filtered list reflects only that category and the URL is shareable.

**Acceptance Scenarios**:

1. **Given** 3 published posts (1 featured) across 2 categories,
   **When** a traveler visits `/en/blog`,
   **Then** the page shows the featured post in a hero section and a paginated list
   (default 12 per page) of all published posts ordered newest-first, with the featured
   post also included in the list.
2. **Given** posts in the "Food & Drink" category,
   **When** a traveler visits `/en/blog?category=food-drink`,
   **Then** only posts in that category appear, the URL is shareable/bookmarkable, and
   the category filter is reflected in the page state.
3. **Given** no published posts exist (or none in the selected category),
   **When** a traveler visits `/en/blog`,
   **Then** the page shows an empty state ("No posts yet") with a call-to-action to
   browse tours, and the listing page is crawlable with `website` Open Graph type and an
   `ItemList` structured-data entry.

---

### User Story 3 - Author and Publish a Blog Post as an Admin (Priority: P3)

An admin editor signs in to the admin panel, navigates to the Content section, and
creates a new blog post. They enter a slug, choose a category, set an author, write the
rich-text body in English (and optionally Spanish/Italian), upload a cover image, pick
related tours, and save as draft. They preview the post on the public frontend via a
signed preview link. Satisfied, they publish immediately or schedule it for a future
date. Every governance action (create, update, publish, archive) is recorded in the
immutable audit log.

**Why this priority**: Authoring is what produces the content the public reads. Without
the admin authoring flow, there is no blog. This story is independently viable: an admin
can create and publish posts even before the public listing/detail pages are polished.

**Independent Test**: Sign in as an admin with the `manage_blog` permission, create a post
with EN content, save as draft, generate a preview token, open the preview URL, then
publish and confirm the post appears on the public listing and in the sitemap.

**Acceptance Scenarios**:

1. **Given** an admin with the `manage_blog` permission,
   **When** they navigate to the admin Content section,
   **Then** they see `BlogPostResource` and `BlogCategoryResource` and can create, edit,
   and view posts and categories.
2. **Given** a draft post with a valid EN title and body,
   **When** the admin generates a preview token and opens `/{locale}/blog/{slug}/preview?token={signed}`,
   **Then** the post renders on the public frontend regardless of draft status, with
   `robots: noindex, follow: false`, and the token is time-limited (e.g. 30 minutes).
3. **Given** a draft post with a future `scheduled_at` timestamp,
   **When** the admin sets status to published and saves,
   **Then** the post is not yet publicly visible (the public scope excludes it until
   `scheduled_at <= now`), but `published_at` is stamped on first publication and an
   audit entry with action `blog.publish` is recorded.
4. **Given** any admin governance action (publish, update, archive),
   **Then** an immutable audit-log entry is written capturing the actor, action, target,
   and before/after state, matching the platform's governance convention.

---

### Edge Cases

- What happens when a traveler requests a blog post in a locale (e.g. `?locale=it`)
  that has no IT content? The API returns the EN content with
  `translation_warning: 'partial_translation'`; the frontend shows a visible indicator.
- What happens when a slug contains uppercase or invalid characters? The request is
  rejected with 422 validation (`^[a-z0-9-]+$`).
- What happens when a related tour is archived after being linked to a published post?
  The blog detail surfaces only published tours; the archived tour is omitted silently.
- What happens when the preview token is expired or tampered? The preview endpoint
  returns 403 with a "Preview link expired" message.
- What happens when a category is deactivated (`is_active = false`)? Its posts remain
  reachable by direct slug and by direct category URL, but it is excluded from the
  category-navigation list on the blog index.
- What happens when two admins edit the same post concurrently? The last save wins (no
  optimistic locking in v1); audit logs capture both before/after states.
- What happens when the sitemap cache is stale after a publish? A queued job flushes
  the sitemap cache key on publish/archive; the next request rebuilds it.
- What happens when a traveler hits the rate limit on the blog listing? The API returns
  429 with a `Retry-After` header; the frontend renders a service-unavailable fallback.

## Requirements *(mandatory)*

### Functional Requirements

**Content Model & Authoring**

- **FR-001**: The system MUST store blog posts with localized title, body, excerpt, and
  meta_description fields keyed by locale (en, es, it), mirroring the existing static-page
  CMS pattern.
- **FR-002**: The system MUST store a single non-localized `slug` per blog post, unique
  across all posts, generated from the English title, validated against `^[a-z0-9-]+$`.
- **FR-003**: The system MUST support three post statuses: `draft`, `published`, and
  `archived`, plus a nullable `scheduled_at` timestamp for future publishing. A post is
  publicly visible iff `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now)`.
- **FR-004**: The system MUST stamp `published_at = published_at ?? now()` on first
  publication (preserving the original timestamp on re-publish), matching the existing
  CMS convention.
- **FR-005**: The system MUST require non-empty English title and body to publish a post.
  Spanish and Italian content are optional; when a requested locale is missing, the system
  MUST return English content with `translation_warning: 'partial_translation'`.
- **FR-006**: The system MUST store blog post bodies as rich HTML authored via a rich-text
  editor in the admin panel, with no markdown or block-editor dependency.
- **FR-007**: The system MUST store an `is_featured` boolean on blog posts for editorial
  curation of the featured/hero section on the blog listing.

**Categories**

- **FR-008**: The system MUST provide a dedicated blog category taxonomy, separate from
  the tour category taxonomy, with a non-localized name, unique slug, description,
  active flag, and display order.
- **FR-009**: The system MUST allow admins to create, edit, and (de)activate blog
  categories via the admin panel.

**Authors**

- **FR-010**: The system MUST link each blog post to an author (an admin user) and store
  optional localized display metadata (display name, bio, avatar) in a one-to-one author
  profile, so the byline and author structured data render consistently.

**Related Tours**

- **FR-011**: The system MUST allow admins to attach related tours to a blog post via an
  explicit many-to-many link with a sort order, and the public blog detail MUST surface
  only published related tours, up to a maximum of 6, ordered by `sort_order`.
  Administrators MAY attach more than 6 related tours, but only the first 6 eligible
  published tours are rendered publicly.

**Related Posts**

- **FR-012**: The public blog detail MUST display up to three related posts from the same
  category (excluding the current post), ordered newest-first, to improve internal linking
  and engagement. If fewer than three eligible same-category posts exist, the remaining
  slots MUST be backfilled with published posts from other categories, ordered newest-first,
  until three total posts are available.

**Public API**

- **FR-013**: The system MUST expose a paginated public listing endpoint
  (`GET /api/public/blog`) supporting category filtering and locale selection, returning
  `{ data, meta: { current_page, last_page, per_page, total } }` with a default page size
  of 12.
- **FR-014**: The system MUST expose a public detail endpoint (`GET /api/public/blog/{slug}`)
  returning the post, an `seo` block (meta_title, meta_description, canonical_url, hreflang
  map), the author profile, the category, related published tours, and `reading_time` as an
  estimated reading time in whole minutes, derived from the post body word count, with a
  minimum value of 1.
- **FR-015**: The system MUST expose a public category endpoint
  (`GET /api/public/blog/category/{slug}`) returning the category and its paginated posts.
- **FR-016**: The system MUST expose a preview endpoint
  (`GET /api/public/blog/{slug}/preview?token={signed}`) returning any-status post when the
  time-limited, post-bound signed token is valid. The token MUST be cryptographically bound
  to the post's current slug and `expires_at`; the endpoint MUST verify the token against
  the requested `{slug}` before returning preview content, so changing a post's slug
  invalidates previously issued preview tokens for that post.
- **FR-017**: All public blog endpoints MUST require a `locale` query parameter validated
  against the supported locales (en, es, it), returning 422 on missing/invalid locale.
- **FR-018**: The system MUST rate-limit public blog endpoints per IP (listing/detail at
  120 requests/minute, sitemap at 10/minute), returning 429 with a `Retry-After` header.

**SEO**

- **FR-019**: Every public blog page MUST include a meta title, meta description, a
  self-canonical URL per locale, and hreflang alternates linking all three locales.
- **FR-020**: The blog detail page MUST include Open Graph metadata with `type = 'article'`
  (published time, modified time, authors, section, image) and a twitter
  `summary_large_image` card.
- **FR-021**: The blog listing and category pages MUST include Open Graph metadata with
  `type = 'website'` and a twitter `summary` card.
- **FR-022**: The blog detail page MUST emit `BlogPosting` and `BreadcrumbList` JSON-LD
  structured data; the blog listing MUST emit `ItemList` structured data.
- **FR-023**: The system MUST include all published blog posts and blog categories in the
  sitemap, with hreflang alternates per post, and MUST regenerate the sitemap cache on
  publish/archive via a queued job.
- **FR-024**: The preview page MUST be excluded from the sitemap and carry
  `robots: noindex, follow: false`.

**Caching & Invalidation**

- **FR-025**: The system MUST cache the blog listing and category listing for five minutes
  and the sitemap for one hour, and MUST NOT cache the blog detail or preview. The
  system MUST invalidate list/category caches and dispatch sitemap regeneration on
  publish, update, and archive. When a scheduled post transitions to publicly visible
  (`scheduled_at <= now`), the system MUST run a queued transition job that re-validates
  the post's scheduling state, invalidates affected list/category caches, and dispatches
  sitemap regeneration, so scheduled content does not remain stale in public discovery or
  the sitemap after its visibility transition.

**Authorization & Audit**

- **FR-026**: Public reads of published blog content MUST require no authentication.
  Preview MUST require a valid signed token (no auth header). Admin authoring MUST require
  an admin session gated by a `manage_blog` permission flag.
- **FR-027**: Every admin governance action on a blog post (create, publish, update, archive) MUST
  write an immutable audit-log entry with action keys `blog.create`, `blog.publish`, `blog.update`, or
  `blog.archive`, capturing actor, target, and before/after state.

**Errors & States**

- **FR-028**: The system MUST return 404 for non-existent, draft, scheduled-future, and
  archived-never-published posts; 410 Gone for archived-previously-published posts; 422
  for invalid locale, slug, or per_page; and 429 for rate limits.
- **FR-029**: The blog listing MUST render a branded empty state ("No posts yet") with a
  call-to-action to browse tours when no published posts exist.
- **FR-030**: The frontend MUST render skeleton loading states while blog content loads
  and a service-unavailable fallback when the API is rate-limited or unreachable.

### Key Entities *(include if feature involves data)*

- **Blog Post**: The editorial article. Localized title, body, excerpt, meta_description
  (keyed by locale); single non-localized slug; status (draft/published/archived);
  scheduled_at; published_at; is_featured; cover image; author (admin user); category;
  related tours; related posts (derived, same category).
- **Blog Category**: Taxonomy grouping posts. Non-localized name, unique slug,
  description, is_active, display order. Separate from tour categories.
- **Author**: The admin user who authored the post. Has a one-to-one Author Profile with
  localized display_name, bio, avatar.
- **Author Profile**: Localized display metadata for an author (display name, bio, avatar
  URL), keyed by locale. One-to-one with the user.
- **Related Tour Link**: An explicit many-to-many link between a blog post and a tour
  (post_id, tour_id, sort_order). Only published tours are surfaced publicly.
- **Preview Token**: A signed, time-limited, post-bound token granting preview access to
  an unpublished post on the public frontend. Never indexed, never cached.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A published blog post is readable on the public website in under 2 seconds
  from first byte to fully-rendered article (including cover image and structured data).
- **SC-002**: The blog listing supports at least 50 published posts with category
  filtering and pagination without page-load degradation exceeding 2 seconds.
- **SC-003**: All three locales (EN, ES, IT) render correct content for fully-translated
  posts, and partially-translated posts fall back to English with a visible
  partial-translation indicator on 100% of such requests.
- **SC-004**: 100% of public blog pages pass an automated SEO audit (meta title, meta
  description, canonical, hreflang, Open Graph, structured data) with zero missing
  required tags.
- **SC-005**: An admin can create, preview, and publish a blog post end-to-end in under
  10 minutes, including setting a category, author, related tours, and cover image.
- **SC-006**: 100% of admin governance actions (publish, update, archive) produce an
  immutable audit-log entry that captures the actor, action, and before/after state.
- **SC-007**: The blog listing and detail pages achieve a Lighthouse Performance score of
  at least 90 and a Lighthouse Accessibility score of at least 95.
- **SC-008**: Related tours on a blog detail page link to the correct published tour detail
  page 100% of the time, and archived tours never appear as related tours.

## Assumptions

- The existing admin panel (Filament) is the sole ratified exception to the platform's
  API-first constitution; blog authoring happens in Filament, and the public frontend
  consumes a read-only blog API. This mirrors the static-page CMS precedent.
- Blog authors are admin users (role = 'admin'); there is no separate author signup or
  registration flow. Author display metadata is optional and localized.
- The blog is editorial content, not a tour listing; it does not violate the platform's
  tours-only discipline because posts are not bookings and merely link to tours for
  discovery.
- Blog search (free-text query) is out of scope for v1; the listing supports category
  filtering and pagination only. Search will be reconsidered when analytics justify it.
- Cover images and author avatars are served from the existing image CDN; the blog adds
  no new image storage infrastructure beyond an admin upload field.
- The existing sitemap generator (backend) is extended to include blog URLs; no separate
  frontend sitemap file is introduced.
- The existing governance audit service is reused for blog audit logging; no new audit
  infrastructure is built.
- The three supported locales (en, es, it) and the English fallback convention are
  inherited from the platform configuration; no new locales are introduced.
- Performance targets match the platform standard (Lighthouse Performance >= 90 for
  public pages); no blog-specific performance budget is set beyond this.
- Tagging is out of scope for v1 (no tag entity); categories are the sole taxonomy.