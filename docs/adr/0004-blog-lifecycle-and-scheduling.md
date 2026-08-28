# Blog lifecycle: draft / published / archived + scheduled publishing

Blog posts use three statuses: `draft`, `published`, `archived`. There is no
`pending_review` or `rejected` state because blog posts are admin-authored in Filament
by trusted editors — unlike tours, which are partner-submitted and need a moderation queue.

Scheduled publishing is modeled via a nullable `scheduled_at` timestamp. A post is publicly
visible iff `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())`.
First publication stamps `published_at = published_at ?? now()` (reuse StaticPage pattern).
Archived posts that were previously published return 410 Gone; archived-never-published
return 404 (reuse tour gating, GetTourDetailAction.php:26-35).

Audit action keys: `blog.publish`, `blog.update`, `blog.archive`. A queued transition job
runs at `scheduled_at` (or a short polling interval) to re-validate the scheduling state,
invalidate list/category caches, and dispatch sitemap regeneration when a scheduled post
becomes publicly visible (resolved in Spec 016 clarification, 2026-08-22).