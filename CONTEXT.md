# Bookly Travel — Blog & Travel Insights Context

The editorial blog (Spec 016) is a content-marketing surface: long-form travel-insight
articles authored by admins in Filament, served to the public Next.js frontend via a
read-only API, integrated with tour discovery through related-tour links.

## Language

**Blog Post**:
The editorial article entity (title, body, excerpt, cover image, category, author,
related tours). Authored in Filament, published via a draft/published/archived lifecycle.
_Avoid_: Article, Post (ambiguous with HTTP POST), Insight.

**Blog Category**:
A taxonomy grouping blog posts (e.g. "Food & Drink", "History"). Admin-authored in a
separate `blog_categories` table (non-localized name/slug, mirroring tour `Category`),
decoupled from tour `categories`. Managed via a `BlogCategoryResource` in Filament.
_Avoid_: Topic, Section, Tour Category.

**Author**:
The admin user who authored a blog post. Reuses the `users` table; display metadata
(name, bio, avatar) may be localized. No separate author signup/registration.
_Avoid_: Writer, Contributor.

**Author Profile**:
The localized display metadata for a blog author (display_name, bio, avatar_url), stored
in a 1:1 `author_profiles` table keyed by JSONB locale. The byline and schema.org `Person`
entity come from this profile.
_Avoid_: Author record, User profile.

**Related Tour**:
A tour linked from a blog post for discovery. Many-to-many via a `blog_post_tours` pivot
(post_id, tour_id, sort_order); only published tours are surfaced on the public blog detail.
_Avoid_: Featured tour, recommended tour (ambiguous with homepage featured tours).

**Related Post**:
A blog post in the same category as the current post, surfaced on the blog detail page
(max 3, excludes the current post). Improves internal linking and engagement.
_Avoid_: Similar post.

**Preview Token**:
A signed, time-limited (e.g. 30 min), post-bound HMAC token allowing an admin to view a
draft/scheduled post on the public frontend before publication. Issued from Filament;
the preview route is `robots: noindex` and never cached or in the sitemap.
_Avoid_: Draft link, Share link.

**Scheduled Publish**:
A blog post with `status = 'published'` and a future `scheduled_at` timestamp. Not yet
publicly visible; becomes visible when `scheduled_at <= now()`. First publication stamps
`published_at = published_at ?? now()`.
_Avoid_: Future post, Deferred publish.

**Partial Translation**:
A blog post whose content for the requested locale is missing; the API returns the English
content with `translation_warning: 'partial_translation'`. EN content is required to publish;
ES/IT are optional.
_Avoid_: Untranslated, fallback post.