# Blog listing: is_featured + newest-first + category filter + 12/page

Blog posts carry an `is_featured` boolean (mirroring tours) with a composite index
`[status, is_featured]`. The listing defaults to newest-first (`published_at desc`).
Featured posts render in a hero/featured section above the paginated list. Category
filtering via `?category={slug}`. Pagination `per_page` default 12 (matching tours).

Empty state: "No posts yet" with a CTA to browse tours (mirroring the "No tours found"
pattern at `search.spec.ts:27-32`).