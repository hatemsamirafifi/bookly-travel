# Feature Specification: Bookly Admin 2.0 — Platform Administration, CMS, Media Library, File Manager & Automatic Translation Engine

- **Spec Number:** `035`
- **Feature Directory:** `specs/035-admin-2.0/`
- **Feature Branch:** `feature/admin-2.0`
- **Governing Constitution:** [Bookly Constitution v2.1.0](file:///f:/Travel%20Website/bookly%20travel/.specify/memory/constitution.md)
- **Verified Predecessors:** `013-admin-moderation`, `016-blog-travel-insights`, `017-stripe-connect-invoicing`, `019-tour-content-data`
- **Status:** Specified & Planned

---

## 1. Overview & Objectives

Bookly Admin 2.0 upgrades the existing Laravel Filament 3.3 administrative platform (`/admin`) into a comprehensive marketplace governance, financial oversight, media/storage management, content management (CMS), and automatic field-level AI translation system.

All capabilities extend the existing Laravel 11 + Filament 3.3 architecture in-place without introducing a separate admin frontend or duplicating existing domain models.

---

## 2. User Stories & Functional Requirements

### US-01: 6-Group Permission-Aware Navigation & Dashboard Analytics (FR-001 to FR-005)
- **FR-001:** Reorganize the Filament sidebar into 6 permission-aware groups: `Dashboard`, `Marketplace Management`, `Finance`, `Content Management`, `Media & Files`, and `Administration`. Links to unauthorized resources MUST be hidden.
- **FR-002:** Upgrade `/admin` Dashboard to display real database metrics: Total/Confirmed/Completed/Cancelled bookings, Gross booking value, Platform revenue, Refund totals, Total travelers, Active/Pending partners, Total/Published/Pending tours, and Hidden/Flagged reviews.
- **FR-003:** Provide Dashboard date-range filtering (`Today`, `Yesterday`, `Last 7 days`, `Last 30 days`, `Last 90 days`, `Custom date range`).
- **FR-004:** Render Dashboard analytics charts/rankings: Bookings over time, Revenue over time, Booking status distribution, Top-performing tours, Top-performing partners, Traveler growth, Partner growth, and Tour publishing activity.
- **FR-005:** Cache expensive dashboard aggregates with permission-aware visibility so financial metrics are hidden from unauthorized roles.

### US-02: Admin Users & Granular Role-Based Access Control (FR-006 to FR-011)
- **FR-006:** Provide `AdminUserResource` and `AdminRoleResource` allowing authorized admins to view, create, edit, activate/deactivate, and suspend admin accounts, assign role templates (`Super Admin`, `Operations Manager`, `Content Manager`, `Finance Manager`, `Support Agent`, `Moderator`, `Analyst`), review activity, and revoke sessions/tokens.
- **FR-007:** Support granular permissions across resources (`view`, `create`, `edit`, `delete`, `approve`, `reject`, `publish`, `unpublish`, `export`, `manage_permissions`, `manage_settings`) while preserving all 10 existing `AdminAuthorizationService::FLAGS`.
- **FR-008:** Prevent privilege escalation: an administrator MUST NOT assign a role or permission flag they do not personally hold.
- **FR-009:** Protect the last active Super Admin from deactivation, suspension, deletion, or removal of Super Admin privileges.
- **FR-010:** Audit all admin user, role, and permission changes via `GovernanceAuditService`.

### US-03: Traveler, Partner, Tour, Booking & Review Management (FR-011 to FR-020)
- **FR-011:** Provide `TravelerResource` listing travelers (`role = 'traveler'`) with name, email, phone, registration date, status, booking count, total booking value, and last activity, plus detail view (profile, bookings, payment summaries, reviews, notifications, admin activity) and suspend/reactivate actions.
- **FR-012:** Extend `PartnerResource` with booking performance, review performance, and activity history while preserving existing onboarding, invitation, and approval gates.
- **FR-013:** Extend `TourResource` with archive action, booking statistics, translation status, and audit history while preserving publishing governance (`rejected` tours cannot publish directly without resubmission).
- **FR-014:** Extend `BookingResource` with voucher details, refund tracking, notification logs, and authorized export while preserving the booking state machine and Stripe payment immutability.
- **FR-015:** Extend `ReviewResource` with flag and resolve moderation actions, partner response inspection, and full audit logging.

### US-04: Read-Only Financial Management & Ledger (FR-021 to FR-024)
- **FR-021:** Provide `FinancialOverviewPage` displaying gross booking value, successful/failed payments, refund totals, platform revenue, transaction volume, and revenue trends.
- **FR-022:** Provide read-only `PaymentResource` (transactions, Stripe references, booking links, amounts, refunds) and read-only `FinancialLedgerResource` (search, date/type/reference/partner filters, authorized export).
- **FR-023:** Provide `ReconciliationReportPage` highlighting discrepancies between booking payment states, `payments`, and `financial_ledger_entries`.
- **FR-024:** Enforce strict immutability: no admin UI or action may edit, delete, or fabricate `FinancialLedgerEntry` or `Payment` records.

### US-05: Unified Media Library & R2 File Manager (FR-025 to FR-034)
- **FR-025:** Store media binaries in Cloudflare R2 (via `MediaStorageService`) and persist metadata (`original_filename`, `object_key`, `mime_type`, `extension`, `size_bytes`, `width`, `height`, `image_format`, `alt_text`, `caption`, `description`, `tags`, `folder_id`, `uploaded_by`, `visibility`) in PostgreSQL.
- **FR-026:** Validate actual MIME type (`finfo`), extension, size, dimensions, and safe object keys; reject executable, HTML, or script-bearing files and path traversal sequences.
- **FR-027:** Process thumbnails and image dimensions asynchronously via retry-safe Redis jobs (`ProcessMediaAssetJob`).
- **FR-028:** Track polymorphic media usage (`media_usages`) across Blog Posts, Tours, Static Pages, and Categories; warn admins and block permanent deletion of referenced assets unless safely replaced.
- **FR-029:** Provide `MediaAssetResource` with grid/list views, search, filters, metadata editing, bulk actions, soft-delete/restore, and a reusable `MediaPicker` modal.
- **FR-030:** Provide `FileManagerPage` and `StorageAnalyticsPage` strictly scoped to Bookly's managed R2 storage disk (folder tree, breadcrumbs, create/rename/move folders with cycle protection, upload/download/rename/move/copy/soft-delete/restore files, storage analytics by type/category/size) with granular permissions.

### US-06: CMS, Self-Hosted HugeRTE, Static Pages, Blog, Revisions & Scheduling (FR-035 to FR-044)
- **FR-035:** Integrate self-hosted HugeRTE (MIT license, zero external CDN or paid API key) with configurable toolbars (`H2-H6`, bold, italic, underline, lists, alignment, blockquotes, links, images via Media Library Picker, tables, horizontal rules, code blocks, undo/redo) for Blog Posts, Tours, and Static Pages.
- **FR-036:** Sanitize all rich text HTML server-side via allowlist-based `HtmlSanitizerService` (`symfony/html-sanitizer`) before storage and rendering.
- **FR-037:** Upgrade `StaticPageResource` with page creation, SEO title/description, Open Graph metadata, featured image, draft/published status, scheduling, signed preview links, revisions, and `GET /api/public/pages/{slug}` consumed by Next.js.
- **FR-038:** Upgrade `BlogPostResource` with `BlogTagResource`, `AuthorProfileResource`, fix `EditBlogPost` form hydration so blank ES/IT fields are not populated with English fallback text, and ensure idempotent scheduled publication via Laravel Scheduler + Redis queues.
- **FR-039:** Track content revisions (`content_revisions`) for Blog Posts and Static Pages with version number, author, timestamp, snapshot diff comparison, restore-as-new-version, and optimistic locking against concurrent overwrites.

### US-07: Automatic Field-Level AI Translation Engine & Health Monitoring (FR-045 to FR-055)
- **FR-045:** English (`en`) is the single source of truth; Spanish (`es`) and Italian (`it`) are automatically translated asynchronously via Redis queues whenever English content is created or updated, saving results into existing translation structures (`blog_posts`, `static_pages`, `tour_translations`, `categories`, `blog_categories`).
- **FR-046:** Perform translation and freshness tracking at the **field level** (`translation_field_states`): modifying English `title` and `meta_description` dispatches translation jobs ONLY for those two fields in ES and IT without regenerating unchanged `body` or `excerpt` fields.
- **FR-047:** Support field-level statuses (`missing`, `pending`, `processing`, `completed`, `outdated`, `failed`, `manually_edited`) and calculated language-level statuses (`complete`, `incomplete`, `processing`, `outdated`, `failed`, `not_started`).
- **FR-048:** Preserve manual edits: when an admin manually edits a translated ES/IT field, record `origin = 'manual'` (`manually_edited`). Subsequent English source changes mark that field `outdated` without automatically overwriting it unless an admin explicitly triggers regeneration.
- **FR-049:** Validate AI output via `TranslationQualityValidator` (non-empty, valid UTF-8, preserved & server-sanitized HTML tags/links/images, SEO length constraints, non-identical to source where applicable) before marking `completed`.
- **FR-050:** Isolate failures by locale and field: a failed Spanish job MUST NOT block Italian translation, and retries MUST use exponential backoff and idempotency keys (`{type}:{id}:{locale}:{field}:{source_hash}`).
- **FR-051:** Embed a Translation Status panel and per-field status badges in Blog, Tour, and Static Page editors with manual recovery actions (generate missing, retry failed, regenerate outdated, regenerate selected fields, regenerate entire language).
- **FR-052:** Provide a global `TranslationHealthDashboard` with metrics, filters, completion %, recovery actions, rate-limited bulk actions, and grouped admin notifications on failure.
- **FR-053:** Enforce public completeness rules: incomplete target-language translations MUST NOT be exposed as partially translated public pages; incomplete locales fall back cleanly to English with `translation_warning` while keeping valid English pages published.

### US-08: Global Search, Operational Monitoring & Audit Governance (FR-056 to FR-060)
- **FR-056:** Provide permission-aware Global Search across Travelers, Partners, Tours, Bookings, Reviews, Blog Posts, Static Pages, Media Assets, and Admin Users.
- **FR-057:** Provide an Operational Monitoring center surfacing pending partner approvals, pending tour reviews, flagged reviews, failed notification deliveries, failed background jobs, payment reconciliation warnings, failed translation jobs, and media/storage errors.
- **FR-058:** Audit all administrative mutations in `governance_audit_logs` with actor, action, target, before/after state, and request context.

