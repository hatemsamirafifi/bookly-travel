# Blog frontend tests: full Playwright + Jest + a11y

Spec 016 frontend tests:
- **Playwright E2E** in `frontend/tests/e2e/blog/`:
  - `blog-list.spec.ts` — pagination, category filter, empty state.
  - `blog-detail.spec.ts` — render, related tours, related posts, author byline,
    reading time, 404.
  - `blog-i18n.spec.ts` — locale switch, hreflang links, canonical per locale.
  - `blog-seo.spec.ts` — meta title/description, OG article type, twitter card,
    BlogPosting JSON-LD, BreadcrumbList, canonical, robots.
- **axe-core a11y**: `blog-list-a11y.spec.ts`, `blog-detail-a11y.spec.ts`
  (`AxeBuilder({page}).analyze()`, `expect(violations).toEqual([])`).
- **Jest**: `BlogCard.test.tsx` co-located, mocked `next/image` + `next-intl`,
  inline typed fixtures (no factories).

All E2E use role/aria locators (matching `search.spec.ts`/`tour-detail.spec.ts`).