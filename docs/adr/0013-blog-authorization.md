# Blog authorization: public no-auth + signed preview + manage_blog flag

- **Public reads** (list, detail, category): no authentication; scope filters
  `status = 'published' AND (scheduled_at IS NULL OR scheduled_at <= now())`.
- **Preview**: signed time-limited token; no auth header required.
- **Admin authoring** (create/edit/publish/archive): Filament session auth (`role === 'admin'`)
  gated by a new `manage_blog` permission flag via `BlogPostPolicy` +
  `AdminAuthorizationService::can($user, 'manage_blog')`. The flag is added to
  `AdminAuthorizationService::FLAGS` alongside `manage_cms`.

Audit action keys: `blog.publish`, `blog.update`, `blog.archive` — logged via
`GovernanceAuditService::log()` within the same DB transaction as the state change
(matching the `cms.*` precedent). `BlogPostPolicy` registered via `Gate::policy()` in
`AppServiceProvider`.

We chose a distinct `manage_blog` flag (not reusing `manage_cms`) so static-page and
blog editorial permissions can be granted independently in the future.