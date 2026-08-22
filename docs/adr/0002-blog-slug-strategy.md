# Blog slugs: single non-localized slug

Blog posts use one shared non-localized `slug` (varchar, unique), generated from the
English title via `Str::slug()`, validated `^[a-z0-9-]+$`. Per-locale slugs are rejected.

Localized SEO differentiation comes from hreflang alternates + translated title/meta/body,
matching the convention used by tours, categories, and `StaticPage` (Spec 006:116,157).
Per-locale slugs would break every existing slug convention and add per-locale uniqueness,
routing, sitemap, and 404 complexity with no precedent in the codebase.