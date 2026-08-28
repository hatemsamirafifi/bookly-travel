# Blog detail page: full detail with related tours + related posts + reading time

The blog post detail page renders: title, cover image (`next/image` + `plaiceholder`),
body HTML (`dangerouslySetInnerHTML`), author byline (name, avatar, bio from
`author_profiles`), `published_at`, category badge, related tours (`TourCard` list, max
3-6, only `Tour::published()` via the `blog_post_tours` pivot), related posts (same
category, max 3, exclude current), and an estimated reading time (from body word count).

Structured data: `BlogPosting` + `BreadcrumbList` (see ADR-0015).