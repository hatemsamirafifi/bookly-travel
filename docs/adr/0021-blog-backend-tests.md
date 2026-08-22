# Blog backend tests: full Pest suite mirroring Search tests

Spec 016 backend tests live in `backend/tests/Feature/Blog/` and mirror the
`Search/` suite:
- `BlogPostTest` — detail 200/404/410/422, locale fallback with `translation_warning`,
  `data.seo` block, related tours only `published`.
- `BlogListTest` — pagination, category filter, featured ordering, empty state,
  422 invalid `locale`/`per_page`.
- `BlogCategoryTest` — category list, slug 404.
- `BlogPreviewTest` — token valid 200, invalid/expired 403.
- `BlogSitemapTest` — blog URLs in sitemap with hreflang, draft excluded,
  `beforeEach Cache::flush()`.
- `BlogAuthorizationTest` — admin `manage_blog` flag, audit keys `blog.publish`/
  `blog.update`/`blog.archive`.

Shared Pest helpers added to `backend/tests/Pest.php`: `makeBlogPost()`,
`makeBlogCategory()`. Direct `Model::create([...])` (no content-model factories;
matching the Search test convention).