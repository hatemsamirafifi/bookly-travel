# Blog structured data: BlogPosting + BreadcrumbList + ItemList

Spec 016 adds three JSON-LD schemas to `frontend/src/components/seo/StructuredData.tsx`:
- **BlogPostingSchema** on the blog detail page: `headline`, `description`, `datePublished`,
  `dateModified`, `author` (`Person`), `image`, `inLanguage`, `publisher` (`Organization`),
  `mainEntityOfPage`.
- **BreadcrumbListSchema** (generalized, not tour-only) on the blog detail and category
  pages: Home > Blog > Category > Post.
- **ItemListSchema** (already exists, reused) on the blog listing page.

We chose `BlogPosting` over the more generic `Article` because `BlogPosting` is the
schema.org type specifically for blog posts and is preferred by Google for blog content.
All schemas emit `<script type="application/ld+json">` via `dangerouslySetInnerHTML`,
matching the existing pattern.