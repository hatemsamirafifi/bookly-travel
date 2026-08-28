# Blog content ownership: Filament CMS + DB model

Spec 016 blog posts are authored in a Filament admin resource and persisted in a Laravel
DB model with JSONB-localized columns, mirroring the existing `StaticPage` CMS pattern.
The public Next.js frontend consumes the content via a read-only API under `/api/public/blog`.

We chose this over markdown/MDX files or a hybrid so the blog reuses governance audit,
the `SitemapController` transformer pattern, scheduling, preview, and the API-first
constitution (Filament admin is the sole ratified exception to API-first, per Spec 013).