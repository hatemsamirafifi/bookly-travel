# Blog canonical URLs: self-canonical per locale + hreflang

Each locale URL is self-canonical (e.g. `/es/blog/foo` has canonical `/es/blog/foo`).
`hreflang` alternates link all 3 locales. The sitemap lists all 3 locale URLs per post
with hreflang alternates.

This matches the tour detail pattern exactly (GetTourDetailAction.php:123-144;
frontend tours/[slug]/page.tsx:12-37), is the correct hreflang implementation for
localized content, and avoids duplicate-content penalties. A single English canonical
for all locales would de-index localized variants and contradict the tour precedent.