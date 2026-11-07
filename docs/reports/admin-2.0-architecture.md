# Bookly Travel — Admin 2.0 Architecture Plan

**Document Status:** Complete  
**Date:** 2026-11-01  
**Spec Reference:** [`specs/035-admin-2.0/spec.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/spec.md)  
**Governing Constitution:** [Bookly Constitution v2.1.0](file:///f:/Travel%20Website/bookly%20travel/.specify/memory/constitution.md)

---

## 1. Architectural Principles & Non-Negotiable Boundaries

1. **Filament 3.3 Internal Administration Boundary:**
   - All administrative UI remains inside Laravel Filament under `/admin` ([`AdminPanelProvider.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Providers/Filament/AdminPanelProvider.php)).
   - Public, traveler, checkout, and partner surfaces remain Next.js 16 applications consuming `/api/public/*` and `/api/partner/*`.
2. **Thin UI / Domain Action & Service Layer:**
   - Filament Resources, Pages, Widgets, and Actions act strictly as presentation and input boundaries.
   - All state mutations, permission assignments, moderation transitions, storage operations, HTML sanitization, revision snapshots, and translation dispatches execute inside dedicated Domain Actions and Services under `app/Domains/*`.
3. **Immutable Governance & Financial Integrity:**
   - [`FinancialLedgerEntry`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/FinancialLedgerEntry.php) and [`GovernanceAuditLog`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/GovernanceAuditLog.php) remain strictly append-only.
   - Admin 2.0 never fabricates balances, never mutates ledger rows, and never bypasses Stripe for payment or refund execution.
4. **Asynchronous Queue-Driven Processing:**
   - Image variant/dimension processing (`ProcessMediaAssetJob`), field-level AI translations (`TranslateContentFieldJob`), scheduled content publication (`PublishScheduledContentJob`), and cache/sitemap invalidation run on Redis queues with idempotent locks and retry-safe backoff.

---

## 2. Information Architecture (6-Group Permission-Aware Navigation)

Configured in [`AdminPanelProvider.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Providers/Filament/AdminPanelProvider.php):

1. **Dashboard (`/admin`):**
   - Platform Overview KPIs, Date-Range Filter (`Today`, `Yesterday`, `7d`, `30d`, `90d`, `Custom`), Booking & Revenue Charts, Top Tours/Partners, Growth Trends, Recent Activity, and Operational Alerts.
2. **Marketplace Management:**
   - `TravelerResource` (`/admin/travelers`)
   - `PartnerResource` (`/admin/partners`)
   - `TourResource` (`/admin/tours`)
   - `CategoryResource` (`/admin/categories`)
   - `DestinationResource` (`/admin/destinations`)
   - `AvailabilityResource` (`/admin/availability`)
   - `BookingResource` (`/admin/bookings`)
   - `ReviewResource` (`/admin/reviews`)
3. **Finance:**
   - `FinancialOverviewPage` (`/admin/finance-overview`)
   - `PaymentResource` (`/admin/payments`)
   - `FinancialLedgerResource` (`/admin/financial-ledger`)
   - `ReconciliationReportPage` (`/admin/reconciliation-reports`)
4. **Content Management (CMS):**
   - `BlogPostResource` (`/admin/blog-posts`)
   - `BlogCategoryResource` (`/admin/blog-categories`)
   - `BlogTagResource` (`/admin/blog-tags`)
   - `AuthorProfileResource` (`/admin/blog-authors`)
   - `StaticPageResource` (`/admin/static-pages`)
   - `ContentRevisionResource` (`/admin/content-revisions`)
   - `TranslationHealthDashboard` (`/admin/translations`)
5. **Media & Files:**
   - `MediaAssetResource` (`/admin/media-library`)
   - `FileManagerPage` (`/admin/file-manager`)
   - `StorageAnalyticsPage` (`/admin/storage-analytics`)
6. **Administration:**
   - `AdminUserResource` (`/admin/admin-users`)
   - `AdminRoleResource` (`/admin/admin-roles`)
   - `OperationalMonitoringPage` (`/admin/operational-monitoring`)
   - `GovernanceAuditResource` (`/admin/governance-audits`)
   - `AuditLogResource` (`/admin/audit-logs`)
   - `Settings` (`/admin/settings`)
   - `DemoContentManager` (`/admin/demo-content`)

Every resource and page implements `canViewAny()` / `canAccess()` wired to `AdminAuthorizationService` so unauthorized items are completely hidden from navigation and return `403 Forbidden` on direct access.

---

## 3. Subsystem Architectures

### 3.1 Role-Based Access Control (RBAC) & Admin Users
- Extends [`AdminAuthorizationService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/AdminAuthorizationService.php) and [`AdminPermission`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/AdminPermission.php) without breaking existing permission flags.
- Introduces `admin_roles` (`slug`, `name`, `description`, `permissions` JSONB, `is_system`) and `admin_role_user` pivot, plus `users.status` (`active`, `inactive`, `suspended`) and `users.suspended_at` / `suspension_reason`.
- Seeded Role Templates: `super_admin`, `operations_manager`, `content_manager`, `finance_manager`, `support_agent`, `moderator`, `analyst`.
- **Security Guards:**
  - *Anti-Escalation:* An administrator can only assign roles or permission flags that are a subset of their own effective permissions.
  - *Last Super Admin Guard:* [`ManageAdminUserAction`] blocks deactivating, suspending, deleting, or removing `super_admin` privileges from the last active Super Admin.

### 3.2 Unified Media Library & R2 File Manager
- **Shared Storage Service (`MediaStorageService`):**
  - Wraps Laravel's `Storage::disk(config('media.disk'))` (`r2` in production using Cloudflare R2 S3-compatible endpoint; managed local disk in testing/local dev).
  - Enforces strict path normalization (rejecting `..`, null bytes, leading slashes), MIME sniffing (`finfo`), extension allowlisting, file size caps, and UUID-prefixed storage keys.
- **Media Library (`MediaAsset`, `MediaFolder`, `MediaTag`, `MediaUsage`):**
  - Tracks content assets, dimensions, alt text, captions, tags, and polymorphic usage references (`blog_post`, `tour`, `static_page`, `category`, `blog_category`).
  - Blocks physical deletion when `media_usages` count > 0 unless an explicit replacement asset is selected or references are detached.
- **File Manager (`FileManagerService`, `FileManagerPage`):**
  - Provides folder hierarchy, breadcrumbs, move/rename/copy/soft-delete/restore, and storage analytics (`StorageAnalyticsService`) strictly scoped to Bookly's R2 bucket/disk—never touching the host server filesystem.

### 3.3 Self-Hosted HugeRTE & Server-Side HTML Sanitization
- **HugeRTE Component (`HugeRteEditor`):**
  - Self-hosted assets under `public/vendor/hugerte/` (MIT licensed, zero external CDN or API key required).
  - Configurable toolbar presets (`blog`, `tour`, `static_page`, `compact`) supporting H2–H6, bold, italic, underline, lists, alignment, blockquotes, links, images, tables, horizontal rules, code blocks, and undo/redo.
  - Integrated with the Media Library Picker modal and validated R2 upload endpoint.
- **Server-Side Sanitization (`HtmlSanitizerService`):**
  - Uses `symfony/html-sanitizer` (already locked at `v7.4.13` in `composer.lock`) with an explicit allowlist of elements and attributes.
  - Strips `<script>`, `<iframe>`, `<object>`, `<embed>`, `<svg>`, inline `on*` event handlers, `style` expressions, and `javascript:` / `vbscript:` / `data:text/html` URLs before persistence.

### 3.4 Automatic Field-Level Translation Engine & Health Monitoring
- **Reuse of Existing Translation Storage:**
  - Blog Posts (`blog_posts` JSONB columns), Static Pages (`static_pages` JSONB columns), Tours (`tour_translations` rows + `tour_translation_states`), and Categories (`categories` / `blog_categories` JSONB columns) remain the single persistence targets for translated strings.
- **Field-Level State Tracking (`translation_field_states`):**
  - Tracks per `(translatable_type, translatable_id, locale, field_name)`:
    - `status`: `missing`, `pending`, `processing`, `completed`, `outdated`, `failed`, `manually_edited`
    - `origin`: `ai` | `manual`
    - `source_hash`: current normalized SHA-256 hash of the English field
    - `translated_source_hash`: SHA-256 hash of the English field that produced the current translation
    - `last_error_code`, `attempts`, `last_translated_at`
- **Automatic Field-Level Workflow (`AutoTranslationService` + `TranslateContentFieldJob`):**
  1. When an admin saves English content, `AutoTranslationService` computes per-field SHA-256 hashes of the English fields and compares them against `translation_field_states`.
  2. Only fields whose English source hash changed or whose ES/IT translation is missing are queued (`TranslateContentFieldJob` with idempotency key `{type}:{id}:{locale}:{field}:{source_hash}`).
  3. If a field has `origin = 'manual'` (`manually_edited`), it is marked `outdated` when English changes but is **never automatically overwritten** unless an admin explicitly triggers regeneration for that field.
  4. `TranslationQualityValidator` verifies non-empty output, valid UTF-8, HTML tag preservation + `HtmlSanitizerService` sanitization, SEO length bounds, and non-identical output before saving into the target record and marking the field `completed`.
- **Translation Health Check Engine (`TranslationHealthCheckService`):**
  - Evaluates actual stored values in `blog_posts`, `static_pages`, and `tour_translations` alongside `translation_field_states` to compute language-level status (`complete`, `incomplete`, `processing`, `outdated`, `failed`, `not_started`) and completion percentage.
  - Public API transformers check language completeness so partially translated ES/IT content is never exposed as a broken hybrid page—incomplete locales fall back cleanly to English with `translation_warning`.

