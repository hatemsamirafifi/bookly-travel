# Tasks: Bookly Admin 2.0 (Spec 035)

- **Spec:** [`specs/035-admin-2.0/spec.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/spec.md)
- **Plan:** [`specs/035-admin-2.0/plan.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/plan.md)
- **Execution Engine:** Sequential delegation via `opencode-delegate` (`scripts/relay.mjs`) with orchestrator gate verification and commits per phase.

---

## Phase A — Foundation, RBAC, Admin Users & 6-Group Navigation
- [x] **T-A01 (P0):** Create additive migration for `admin_roles`, `admin_role_user`, and `users` status/suspension columns (`status`, `suspended_at`, `suspension_reason`) + `AdminRole` model and seeder for default role templates (`Super Admin`, `Operations Manager`, `Content Manager`, `Finance Manager`, `Support Agent`, `Moderator`, `Analyst`).
- [x] **T-A02 (P0):** Extend `AdminAuthorizationService`, `User::canAccessPanel()`, and `AdminPermissionMiddleware` to evaluate role + direct permission flags, enforce active user status, block privilege escalation (`assertCanGrant`), and protect the last active Super Admin.
- [x] **T-A03 (P0):** Implement `ManageAdminUserAction` and `ManageAdminRoleAction` with session/Sanctum token revocation and `GovernanceAuditService` logging; build `AdminUserResource` and `AdminRoleResource`; reorganize `AdminPanelProvider` into the 6 permission-aware navigation groups.
- [x] **T-A04 (P0):** Add Pest feature & Filament tests (`AdminUserManagementTest`, `RolePermissionEscalationTest`) verifying RBAC, anti-escalation, last-Super-Admin protection, and navigation visibility.

## Phase B — Core Marketplace Administration & Analytics Dashboard
- [x] **T-B01 (P1):** Implement `DashboardAnalyticsService` with date-range filtering (`Today`, `Yesterday`, `7d`, `30d`, `90d`, `Custom`) and cached aggregates; upgrade `Dashboard.php`, `PlatformOverviewWidget`, and add chart/ranking widgets (Bookings/Revenue over time, Status distribution, Top tours/partners, Traveler/Partner growth, Tour publishing activity).
- [x] **T-B02 (P1):** Implement `TravelerResource` (`ListTravelers`, `ViewTraveler`, `EditTraveler`), `TravelerPolicy`, `SuspendTravelerAction`, and `ReactivateTravelerAction` with booking/payment/review/notification infolists and audit logging.
- [x] **T-B03 (P1):** Upgrade `PartnerResource` (booking/review performance & activity history), `TourResource` (`ArchiveTourAction`, booking performance, audit trail, `DestinationResource`), `BookingResource` (voucher status, refund tracking, notifications, authorized export), and `ReviewResource` (`FlagReviewAction`, moderation resolution).
- [x] **T-B04 (P1):** Add Pest tests for Dashboard date filters/charts, `TravelerResource`, and upgraded Partner/Tour/Booking/Review workflows.

## Phase C — Financial Administration & Read-Only Ledger
- [x] **T-C01 (P1):** Implement `FinancialOverviewService`, `FinancialOverviewPage`, read-only `PaymentResource`, read-only `FinancialLedgerResource` (with date/type/booking/payment/partner filters and authorized export), and `ReconciliationReportPage`.
- [x] **T-C02 (P1):** Add Pest tests (`FinancialAdminFilamentTest`) verifying read-only immutability, minor-unit accuracy, reconciliation detection, and permission gating.

## Phase D & E — Media Infrastructure & Unified Media Library
- [x] **T-D01 (P1):** Create additive migration for `media_folders`, `media_assets`, `media_tags`, `media_asset_tag`, and `media_usages`; create Eloquent models (`MediaFolder`, `MediaAsset`, `MediaTag`, `MediaUsage`).
- [x] **T-D02 (P1):** Implement `MediaStorageService` (R2 + local/test disk abstraction, safe UUID keys, path traversal prevention), `UploadMediaAssetAction` (MIME/magic-byte, extension, size, dimension validation), `ProcessMediaAssetJob` (async thumbnail/dimension extraction), `MediaUsageTrackerService`, and `DeleteMediaAssetAction` (blocking deletion of referenced assets without replacement).
- [x] **T-E01 (P1):** Build `MediaAssetResource` (grid/list view, search/filters, upload modal, metadata/alt-text editor, tag management, usage reference inspection, soft-delete/restore) and reusable `MediaPicker` Filament form component.
- [x] **T-E02 (P1):** Add Pest feature, Filament, and security tests (`MediaLibraryTest`, `MediaSecurityTest`) covering MIME spoofing rejection, usage tracking, safe replacement, and picker integration.

## Phase F — R2 File Manager & Storage Analytics
- [x] **T-F01 (P1):** Implement `FileManagerService` (folder create/rename/move with cycle & conflict guards, file upload/download/rename/move/copy/soft-delete/restore) and `StorageAnalyticsService` (usage by type/category, largest/recent files).
- [x] **T-F02 (P1):** Build `FileManagerPage` and `StorageAnalyticsPage` in Filament with granular file permissions and `GovernanceAuditService` logging for destructive actions; add Pest tests (`FileManagerTest`).

## Phase G — CMS, Self-Hosted HugeRTE, Sanitization, Static Pages, Blog, Revisions & Scheduling
- [x] **T-G01 (P1):** Create additive migration for `content_revisions`, `blog_tags`, `blog_post_tags`, and new CMS columns on `blog_posts` (`og_image_url`, `media_asset_id`, `version`) and `static_pages` (`meta_title`, `featured_image_url`, `og_image_url`, `scheduled_at`, `version`).
- [x] **T-G02 (P1):** Implement server-side allowlist `HtmlSanitizerService` (`symfony/html-sanitizer`) and self-hosted `HugeRteEditor` Filament field component (`public/vendor/hugerte/`) with configurable toolbars and Media Picker integration; apply across `BlogPostResource`, `StaticPageResource`, and `TourResource`.
- [x] **T-G03 (P1):** Implement `ContentRevisionService` (version snapshots, diff comparison, restore-as-new-version, optimistic locking) and `ContentRevisionResource` / relation manager.
- [x] **T-G04 (P1):** Upgrade `StaticPageResource` (create page, scheduling, preview links via `PreviewTokenService`) and expose `GET /api/public/pages/{slug}` + Next.js dynamic rendering with fallback; upgrade `BlogPostResource` (fix `EditBlogPost` EN->ES/IT hydration bug, add `BlogTagResource` and `AuthorProfileResource`, register scheduled content sweep in `routes/console.php`).
- [x] **T-G05 (P1):** Add Pest tests for `HtmlSanitizerService` XSS payloads, HugeRTE form saving, content revision restore, optimistic locking, scheduled publication, and public Static Pages API.

## Phase H — Automatic Field-Level AI Translation Engine & Translation Health Monitoring
- [x] **T-H01 (P0):** Create additive migration for `translation_field_states` (`translatable_type`, `translatable_id`, `locale`, `field_name`, `status`, `origin`, `source_hash`, `translated_source_hash`, `last_error_code`, `attempts`, `last_translated_at`).
- [x] **T-H02 (P0):** Implement provider-agnostic `ContentTranslatorContract`, `GeminiContentTranslator`, and `TranslationQualityValidator` (validating non-empty, UTF-8, preserved & sanitized HTML structure, SEO field bounds, and non-identical text).
- [x] **T-H03 (P0):** Implement `AutoTranslationService`, `TranslateContentFieldJob` (idempotent per `{type}:{id}:{locale}:{field}:{source_hash}` with exponential backoff and locale failure isolation), manual-edit detection & protection (`origin = 'manual'` / `manually_edited` preserved on English update and marked `outdated`), and `TranslationHealthCheckService` (inspecting actual stored values in `blog_posts`, `static_pages`, and `tour_translations` to compute field and language statuses: `complete`, `incomplete`, `processing`, `outdated`, `failed`, `not_started`).
- [x] **T-H04 (P0):** Wire automatic translation triggers into `UpdateBlogPostAction`, `UpdateStaticPageAction`, and Tour save flows; enforce public completeness rules in `BlogPostDetailTransformer` and `StaticPageController` so incomplete target locales fall back cleanly to English without exposing half-translated pages.
- [x] **T-H05 (P1):** Add Translation Status sections, field-level status badges, and recovery actions (generate missing, retry failed, regenerate outdated/selected/language) to `BlogPostResource`, `StaticPageResource`, and `TourResource`; build the global `TranslationHealthDashboard` Filament page with metrics, filters, batched bulk actions, and grouped failure notifications.
- [x] **T-H06 (P0):** Add comprehensive Pest & Filament tests (`AutoTranslationEngineTest`, `TranslationHealthCheckTest`, `TranslationDashboardFilamentTest`) covering all 17 translation verification requirements.

## Phase I — Global Search & Operational Monitoring
- [x] **T-I01 (P2):** Configure permission-aware Global Search across all 9 admin resources (Travelers, Partners, Tours, Bookings, Reviews, Blog Posts, Static Pages, Media Assets, Admin Users).
- [x] **T-I02 (P2):** Build `OperationalMonitoringPage` and dashboard widgets surfacing failed background jobs, failed notification deliveries, payment reconciliation warnings, failed translation jobs, and media/storage errors; add Pest tests (`GlobalSearchTest`, `OperationalMonitoringTest`).

## Phase J — Complete Regression, Playwright E2E & Release Documentation
- [x] **T-J01 (P0):** Add and execute Playwright E2E tests (`frontend/tests/e2e/admin-2.0.spec.ts`) covering all 20 admin workflows and run full backend (`pest`, `pint`, `phpstan`) and frontend (`jest`, `tsc`, `eslint`) gates.
- [x] **T-J02 (P0):** Produce final evidence-based reports (`docs/reports/admin-2.0-test-report.md`, `docs/reports/admin-2.0-final-regression.md`, `docs/reports/admin-2.0-release-readiness.md`).

## Post-Audit Remediation & Verification (Findings F-001 through F-008)
- [x] **F-001 (HIGH):** Enforce static page atomic language fallback and read-time HTML sanitization via `HtmlSanitizerService` in `StaticPageController`. (`commit 79c87fb`)
- [x] **F-002 (HIGH):** Preserve secondary locale (ES/IT) translations on blank form save in `CreateStaticPage`, `EditStaticPage`, and `UpdateStaticPageAction`. (`commit 679e240`)
- [x] **F-003 (HIGH):** Enforce translation field-state public eligibility gating via `isLocaleEligible()` across `StaticPage` and `BlogPost`. (`commit 9a04c7a`)
- [x] **F-004 (HIGH):** Expand Playwright E2E suite to 15 operational workflows and execute against isolated server on `http://127.0.0.1:18080` (15/15 passed). (`commit 170ba67`)
- [x] **F-005 (MEDIUM):** Integrate relational `TourTranslation` and `TourTranslationState` with `AutoTranslationService` and Tour Filament pages. (`commit f29baff`)
- [x] **F-006 (MEDIUM):** Complete media editor and revision integration, modal isolation, and optimistic concurrency locking. (`commit 8e4d803`)
- [x] **F-008 (MEDIUM):** Resolve admin Pint code styling (711 files 0 issues) and PHPStan / Larastan level 5 errors (363 files 0 errors). (`commit 178657c`)
- [x] **F-007 (LOW):** Normalize trailing newlines in phase reports (`commit 87ffaa6`) and provide pre-merge collision prevention documentation.
- [x] **Audit v2 Reports:** Generate comprehensive v2 release readiness reports (`docs/reports/admin-2.0-remediation-report.md`, `docs/reports/admin-2.0-security-reverification.md`, `docs/reports/admin-2.0-playwright-e2e-report.md`, `docs/reports/admin-2.0-final-regression-v2.md`, `docs/reports/admin-2.0-merge-readiness-v2.md`).


