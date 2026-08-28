# Blog authors: FK to users + localized author_profiles table

Blog posts carry an `author_id` FK→`users` (reuse the users table, mirroring
`StaticPage.updated_by`). A small `author_profiles` table (1:1 with users) stores
localized `display_name`, `bio`, `avatar_url` as JSONB keyed by locale. The byline and
schema.org `Person` entity come from this profile.

We rejected a dedicated `authors` table (heavier, no precedent) and a free-text byline
(no author pages, no structured-data Person, no governance link). A separate
`author_profiles` table keeps blog-only fields off the shared `users` table.