# Blog preview: signed token + public preview route

Editors preview unpublished posts on the public frontend via a signed, time-limited
token. Filament issues an HMAC token bound to `slug + expires_at` (e.g. 30-min TTL).
The public API exposes `GET /api/public/blog/{slug}/preview?token=...` returning the post
regardless of status if the token is valid and unexpired and the token's bound slug
matches the requested `{slug}`. The frontend renders it at
`/[locale]/blog/[slug]/preview` with `robots: { index: false, follow: false }` and no
sitemap inclusion. Only authenticated admins can generate the token from the Filament
Edit page. Changing a post's slug invalidates previously issued preview tokens for that
post (resolved in Spec 016 clarification, 2026-08-22).

We rejected Filament-View-only (fails to preview the rendered Next.js page/SEO) and
admin-session-on-public-API (couples public API to admin auth, risks leaking drafts into
caches). The signed token decouples preview from admin session, is time-limited, and is
never indexed.