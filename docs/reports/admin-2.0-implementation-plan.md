# Bookly Travel — Admin 2.0 Implementation Plan

**Document Status:** Complete  
**Date:** 2026-11-01  
**Spec Reference:** [`specs/035-admin-2.0/plan.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/plan.md)  
**Task Breakdown:** [`specs/035-admin-2.0/tasks.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/tasks.md)

---

## 1. Execution Model & Delegation Strategy

Implementation follows the Spec Kit workflow and executes sequentially through `opencode-delegate` (`scripts/relay.mjs`), with the orchestrator reviewing every diff, running backend/frontend verification gates (`pest`, `pint`, `phpstan`, `jest`, `tsc`, `playwright`), and committing each verified phase on `feature/admin-2.0`.

---

## 2. Phased Delivery Roadmap

### Phase A — Foundation, RBAC, Admin Users & Navigation
- **Migrations:** `admin_roles`, `admin_role_user`, `users` status/suspension columns.
- **Domain Services & Actions:** Extend `AdminAuthorizationService` with role templates (`Super Admin`, `Operations Manager`, `Content Manager`, `Finance Manager`, `Support Agent`, `Moderator`, `Analyst`) and granular action flags while preserving all 10 existing flags; `ManageAdminUserAction` and `ManageAdminRoleAction` with anti-escalation and last-Super-Admin guards.
- **Filament UI:** Reorganize `AdminPanelProvider` into the 6 permission-aware navigation groups (`Dashboard`, `Marketplace Management`, `Finance`, `Content Management`, `Media & Files`, `Administration`); build `AdminUserResource` and `AdminRoleResource`.
- **Verification:** Pest tests for RBAC, privilege-escalation prevention, last-Super-Admin protection, session/token revocation, and navigation visibility.

### Phase B — Core Marketplace Administration & Analytics Dashboard
- **Dashboard & Analytics:** Upgrade `Dashboard` with date filter (`Today`, `Yesterday`, `7d`, `30d`, `90d`, `Custom`), `DashboardAnalyticsService` (cached aggregates), expanded KPI cards, and 8 chart/ranking widgets.
- **Traveler Management:** `TravelerResource` (`ListTravelers`, `ViewTraveler`, `EditTraveler`), `SuspendTravelerAction`, `ReactivateTravelerAction` with booking history, payment summaries, reviews, and audit logs.
- **Partner, Tour, Booking & Review Upgrades:**
  - `PartnerResource`: add booking/review performance stats and activity history to detail view.
  - `TourResource`: add `ArchiveTourAction`, booking performance stats, and audit history to detail view; add `DestinationResource`.
  - `BookingResource`: add voucher status, refund tracking details, notification history, and authorized CSV export.
  - `ReviewResource`: add `FlagReviewAction`, moderation resolution workflow, and extended filters.
- **Verification:** Pest feature & Filament tests covering Dashboard date filters, Traveler suspension/reactivation, Partner/Tour/Booking/Review upgrades.

### Phase C — Financial Administration
- **Filament UI & Read-Only Services:**
  - `FinancialOverviewPage` & `FinancialMetricsWidget` (Gross booking value, successful/failed payments, refund totals, platform revenue, trends).
  - `PaymentResource` (read-only inspection of `Payment` records, Stripe references, booking links, refund records).
  - `FinancialLedgerResource` (read-only Filament interface for `FinancialLedgerEntry` with date/type/reference/partner filters and authorized export).
  - `ReconciliationReportPage` (detecting mismatches between bookings, payments, and ledger entries).
- **Verification:** Pest tests proving zero mutation capabilities on payments/ledger and verifying accurate minor-unit calculations and permission gates.

### Phase D & E — Media Infrastructure & Media Library
- **Migrations:** `media_folders`, `media_assets`, `media_tags`, `media_asset_tag`, `media_usages`.
- **Domain Services & Jobs:** `MediaStorageService` (R2 + local/test disk abstraction), `UploadMediaAssetAction` (MIME/magic-byte, extension, size, dimension validation), `ProcessMediaAssetJob` (thumbnails & dimension extraction), `MediaUsageTrackerService` (tracks references in Blog, Tours, Static Pages, Categories), `DeleteMediaAssetAction` (blocks deletion when referenced unless replaced).
- **Filament UI:** `MediaAssetResource` with grid/list toggle, upload modal, metadata/alt-text editor, tag assignment, usage reference viewer, soft-delete/restore, and reusable `MediaPicker` component.
- **Verification:** Pest tests for upload validation, executable rejection, usage tracking, reference-protected deletion, and Media Picker selection.

### Phase F — R2 File Manager & Storage Analytics
- **Domain Services:** `FileManagerService` (folder creation, rename, move with cycle/conflict detection, file move/copy/rename/soft-delete/restore) and `StorageAnalyticsService` (total bytes, object count, breakdown by MIME/category, largest/recent files).
- **Filament UI:** `FileManagerPage` (folder tree, breadcrumbs, grid/list views, file preview/details) and `StorageAnalyticsPage` gated by granular file permissions.
- **Verification:** Pest tests for folder tree operations, recursive move safety, path traversal rejection, and storage analytics calculations.

### Phase G — CMS, Self-Hosted HugeRTE, Static Pages, Blog, Revisions & Scheduling
- **Migrations:** `content_revisions`, `blog_tags`, `blog_post_tags`, additive columns on `blog_posts` and `static_pages`.
- **Rich Text & Sanitization:** Self-hosted `HugeRteEditor` Filament field (`public/vendor/hugerte/`) with configurable presets and Media Library Picker integration; server-side `HtmlSanitizerService` (`symfony/html-sanitizer` allowlist).
- **Revisions & Optimistic Locking:** `ContentRevisionService` recording snapshots on Blog Post and Static Page saves, revision diff comparison, restore-as-new-version action, and concurrent-edit version check.
- **Static Pages & Blog Upgrades:**
  - `StaticPageResource`: add `CreateStaticPage`, HugeRTE body, SEO/OG fields, scheduling, preview links (`PreviewTokenService`), and `GET /api/public/pages/{slug}` + Next.js integration.
  - `BlogPostResource`: fix `EditBlogPost` form hydration bug (stop copying EN into blank ES/IT fields), integrate HugeRTE, add `BlogTagResource` and `AuthorProfileResource`, and register idempotent scheduled content publisher in `routes/console.php`.
- **Verification:** Pest tests for XSS sanitization, revision snapshots/restoration, scheduled publishing idempotency, preview tokens, and public Static Page API.

### Phase H — Automatic Field-Level AI Translation Engine & Health Monitoring
- **Migrations:** `translation_field_states`.
- **Domain Services & Jobs:**
  - `ContentTranslatorContract` & `GeminiContentTranslator` (provider-agnostic abstraction supporting HTML & plain text fields).
  - `AutoTranslationService` (detects created/modified EN fields on save, computes per-field SHA-256 hashes, skips unchanged fields, preserves `manually_edited` fields by marking them `outdated` without overwriting, and dispatches `TranslateContentFieldJob` per changed field & locale).
  - `TranslationQualityValidator` (verifies non-empty, valid UTF-8, preserved & sanitized HTML structure, SEO length rules, and non-identical output).
  - `TranslationHealthCheckService` (inspects actual stored field values in `blog_posts`, `static_pages`, and `tour_translations` to compute field-level and language-level statuses: `complete`, `incomplete`, `processing`, `outdated`, `failed`, `not_started`).
- **Filament UI & Public Guard:**
  - Translation Status section & field-level status badges in Blog, Tour, and Static Page editors with manual recovery actions (generate missing, retry failed, regenerate outdated/selected/language).
  - `TranslationHealthDashboard` Filament page with global metrics, filters, completion %, and rate-limited bulk actions.
  - Public transformer guard ensuring incomplete target-language translations fall back cleanly to English with `translation_warning`.
- **Verification:** All 17 automated translation test scenarios (field-level diffing, manual-edit preservation, failure isolation between ES and IT, quality validation, health calculation, and public fallback guard).

### Phase I — Global Search & Operational Monitoring
- **Global Search:** Configure permission-aware Filament Global Search across Travelers, Partners, Tours, Bookings, Reviews, Blog Posts, Static Pages, Media Assets, and Admin Users.
- **Operational Monitoring:** `OperationalMonitoringPage` & dashboard alert widgets surfacing pending approvals, flagged reviews, failed queue jobs, failed notification deliveries, failed translation jobs, media processing failures, and payment reconciliation warnings.
- **Verification:** Pest tests for permission-filtered global search and operational alert visibility.

### Phase J — Final Regression, E2E Suite & Release Documentation
- Execute full backend Pest suite, frontend Jest/TypeScript checks, and Playwright E2E admin workflows.
- Complete final reports: `docs/reports/admin-2.0-test-report.md`, `docs/reports/admin-2.0-final-regression.md`, and `docs/reports/admin-2.0-release-readiness.md`.

