# Bookly Travel — Admin 2.0 Codebase Audit Report

**Document Status:** Complete (Phase Zero Read-Only Audit)  
**Date:** 2026-11-01  
**Target Surface:** Laravel Filament Admin Dashboard (`/admin`), Admin API (`/api/admin`), Public Content APIs (`/api/public`), and Next.js Public Content Rendering  
**Current Branch:** `fix/master-admin-audit-remediation`  
**Governing Reference:** [Bookly Constitution v2.1.0](file:///f:/Travel%20Website/bookly%20travel/.specify/memory/constitution.md)

---

## 1. Existing Admin Architecture & Stack Compatibility

### 1.1 Installed Versions & Stack Baseline
Verified directly from [`backend/composer.json`](file:///f:/Travel%20Website/bookly%20travel/backend/composer.json), [`backend/Dockerfile`](file:///f:/Travel%20Website/bookly%20travel/backend/Dockerfile), and [`frontend/package.json`](file:///f:/Travel%20Website/bookly%20travel/frontend/package.json):

| Layer | Installed Package / Runtime | Version | Notes |
|---|---|---|---|
| **Backend Runtime** | PHP (`php:8.3-fpm-alpine`) | `8.3` (`^8.2` constraint) | Extensions: `pdo_pgsql`, `pdo_sqlite`, `bcmath`, `gd`, `zip`, `intl`, `pcntl`, `redis` |
| **Backend Framework** | `laravel/framework` | `^11.0` | API-first architecture + Filament internal admin |
| **Admin Framework** | `filament/filament` | `^3.3` | Configured in [`AdminPanelProvider.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Providers/Filament/AdminPanelProvider.php) at `/admin` |
| **Authentication** | `laravel/sanctum` | `^4.0` | Session auth for Filament (`/admin`); Sanctum tokens for API (`/api/admin`) |
| **Search** | `laravel/scout` + `meilisearch/meilisearch-php` | `^11.1` / `^1.10` | Used for public tour search (`IndexTourAction`, `SearchToursAction`) |
| **Settings** | `spatie/laravel-settings` | `^3.9` | Powers `GeneralSettings`, `SeoSettings`, `ContactSettings`, `BookingSettings` |
| **Payments** | `stripe/stripe-php` | `^13.0` | Stripe PaymentIntents, Connect Express, Hosted Invoices, Webhooks |
| **HTML Sanitization** | `symfony/html-sanitizer` | `v7.4.13` (in `composer.lock`) | Present in vendor lockfile; not yet wired into CMS/Blog/Tour persistence |
| **Object Storage** | `config/filesystems.php` (`local`, `public`, `s3`) | Laravel Flysystem | `s3` disk configured in [`filesystems.php`](file:///f:/Travel%20Website/bookly%20travel/backend/config/filesystems.php#L50-L61); `league/flysystem-aws-s3-v3` not yet required in `composer.json` |
| **Frontend Framework** | `next` / `react` / `next-intl` | `16.2.3` / `19.2.4` / `^4.9.1` | Renders public Blog, Tours, and Static Pages (`en`, `es`, `it`) |

### 1.2 Admin Panel Provider & Navigation Structure
[`AdminPanelProvider.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Providers/Filament/AdminPanelProvider.php#L25-L81) registers:
- Brand: `"Bookly Admin"` with custom logo (`filament.admin.logo`), `Plus Jakarta Sans` font, Bookly Navy (`#0A2540`) primary color, and Bookly Gold (`#FFB800`) warning color.
- Current Navigation Groups: `Demo Content`, `Content`, `Platform`, `Operations`, `Monitoring`.
- Auto-discovery for `app/Filament/Resources`, `app/Filament/Pages`, and `app/Filament/Widgets`.

### 1.3 Authorization & Permission Architecture
- **Panel Access Gate:** [`User::canAccessPanel()`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Models/User.php#L134-L137) checks `$this->role === 'admin'`.
- **Granular Capability Flags:** [`AdminAuthorizationService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/AdminAuthorizationService.php#L22-L33) evaluates per-user JSONB boolean flags stored in [`AdminPermission`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/AdminPermission.php) (`admin_permissions.flags`):
  - `manage_tours`, `manage_partners`, `manage_bookings`, `moderate_reviews`, `view_all_analytics`, `manage_users`, `manage_settings`, `manage_cms`, `manage_blog`, `view_audit_log`.
- **API Middleware:** `AdminPermissionMiddleware` (`admin.can:<flag>`) guards `/api/admin/*` routes in [`backend/routes/api/admin.php`](file:///f:/Travel%20Website/bookly%20travel/backend/routes/api/admin.php).

---

## 2. Existing Features & Implementation Status

| Module | Existing Implementation | Status | Key Files |
|---|---|---|---|
| **1. Platform Overview & Analytics** | 5 KPI stat cards (`Today's Bookings`, `Monthly Revenue`, `Pending Partners`, `Pending Tours`, `Flagged Reviews`), 7-day booking trend sparkline, 4 moderation queue shortcut tiles, and 10-row recent bookings widget. | **Partial** (Missing date range filters, revenue/booking/growth charts, top tours/partners, traveler/refund/cancelled metrics) | [`Dashboard.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Pages/Dashboard.php), [`PlatformOverviewWidget.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Widgets/PlatformOverviewWidget.php), [`QueueShortcutsWidget.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Widgets/QueueShortcutsWidget.php), [`RecentBookingsWidget.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Widgets/RecentBookingsWidget.php) |
| **2. Admin Users & RBAC** | `admin_permissions` table with 10 boolean flags in JSONB; `AdminAuthorizationService`; `GrantAdminBlogPermission` CLI command. | **Partial** (No Filament `AdminUserResource`, no Role templates/models, no UI to create/edit/suspend admins or assign roles/permissions, no last-super-admin guard) | [`AdminAuthorizationService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/AdminAuthorizationService.php), [`AdminPermission.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/AdminPermission.php) |
| **3. Traveler Management** | `users` table (`role = 'traveler'`), lockout fields (`failed_login_count`, `locked_until`), bookings/reviews/wishlists relations. | **Missing in Admin** (`manage_users` flag exists in `AdminAuthorizationService` but no `TravelerResource` or domain actions exist) | [`User.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Models/User.php) |
| **4. Partner Management** | `PartnerResource` with list, filters, detail infolist, `InvitePartnerAction`, `ApprovePartnerAction`, `RejectPartnerAction`, `SuspendPartnerAction`, `ReinstatePartnerAction`. | **Implemented (Needs Upgrade)** (Missing booking/review performance summaries and partner activity history in detail view) | [`PartnerResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/PartnerResource.php), [`ApprovePartnerAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Actions/ApprovePartnerAction.php) |
| **5. Tour Management & Moderation** | `TourResource` with list, create/edit forms, detail infolist, translation status badges, `ApproveTourAction`, `RejectTourAction`, `UnpublishTourAction`, bulk publish/reject; `CategoryResource`; `AvailabilityResource`. | **Implemented (Needs Upgrade)** (Needs archive action, HugeRTE integration on descriptive fields, booking statistics & audit history on detail view, destination management) | [`TourResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/TourResource.php), [`CategoryResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/CategoryResource.php), [`AvailabilityResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/AvailabilityResource.php) |
| **6. Booking Management** | `BookingResource` with list, status transitions (`completed`, `no_show`, `expired`, `cancellation_requested`, `cancelled`), detail infolist with payment & embedded domain/governance audit trails. | **Implemented (Needs Upgrade)** (Needs voucher inspection, refund tracking details, notification history, and authorized export) | [`BookingResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/BookingResource.php), [`TransitionBookingStatusAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Actions/TransitionBookingStatusAction.php) |
| **7. Reviews Management** | `ReviewResource` with list, hide/reinstate row & bulk actions, detail infolist with partner response and moderation audit history. | **Implemented (Needs Upgrade)** (Needs explicit flag/resolve moderation actions and extended search/filters) | [`ReviewResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/ReviewResource.php), [`HideReviewAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Reviews/Actions/HideReviewAction.php) |
| **8. Financial Management & Ledger** | Immutable `FinancialLedgerEntry` model, `Payment` model, `LedgerService`, and `GET /api/admin/financial-ledger` endpoint. | **Missing in Filament UI** (No Financial Overview page/widgets, no `PaymentResource` / Transaction inspection UI, no read-only `FinancialLedgerResource`, no reconciliation reports) | [`FinancialLedgerEntry.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/FinancialLedgerEntry.php), [`Payment.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/Payment.php), [`FinancialLedgerController.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Controllers/Admin/FinancialLedgerController.php) |
| **9. Media Library** | Only `tour_media` table for tour galleries and basic `FileUpload` on `BlogPostResource` (`disk('public')`). `UploadController` has a mock presigned URL stub. | **Missing** (No central Media Library, no media assets/metadata/tags/usage tables, no R2 storage service, no Media Picker) | [`TourMedia.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Models/TourMedia.php), [`UploadController.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Controllers/UploadController.php) |
| **10. File Manager** | None. | **Missing** (No R2 File Manager page, folder tree, file operations, or storage analytics) | — |
| **11. CMS & HugeRTE Editor** | `BlogPostResource` uses default Filament `RichEditor` (Trix); `StaticPageResource` uses plain `Textarea`. | **Partial** (HugeRTE not installed; no server-side HTML sanitization; no Open Graph image fields; no tags resource) | [`BlogPostResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/BlogPostResource.php), [`StaticPageResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/StaticPageResource.php) |
| **12. Static Pages Management** | `StaticPage` model (`slug`, `status`, JSONB `title`, `body`, `meta_description`), `StaticPageResource` (list, edit, view), `UpdateStaticPageAction`. | **Partial** (No create action in Filament resource, no `meta_title` / `og_image` / `scheduled_at`, no public API endpoint `/api/public/pages/{slug}`, Next.js `/privacy` and `/terms` are hardcoded i18n strings instead of consuming `StaticPage` API) | [`StaticPage.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/StaticPage.php), [`StaticPageResource.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/StaticPageResource.php), [`privacy/page.tsx`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/app/[locale]/(public)/privacy/page.tsx) |
| **13. Blog Management** | `BlogPost`, `BlogCategory`, `AuthorProfile`, `BlogPostResource`, `BlogCategoryResource`, `UpdateBlogPostAction`, `PreviewTokenService`, `PublishScheduledBlogPostJob`. | **Implemented (Needs Upgrade)** (Needs blog tags, AuthorProfile management in Filament, HugeRTE editor, Media Library picker, revisions, AI translation workflow, and scheduler fallback for scheduled posts) | [`BlogPost.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Models/BlogPost.php), [`UpdateBlogPostAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php), [`PreviewTokenService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Services/PreviewTokenService.php) |
| **14. AI Content Translation & Health Monitoring** | Implemented at record-level for **Tours** via `TourContentTranslator` contract, `GeminiTourTranslator`, `TourTranslationService`, `TourTranslationState`, and `GenerateTourTranslationJob`. | **Partial** (Tours have record-level hash tracking; no field-level translation state/origin tracking, no automatic EN→ES/IT translation on Blog Posts or Static Pages, no manual-edit preservation, no Translation Health Check Engine or Translation Monitoring Dashboard) | [`TourTranslationService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Services/TourTranslationService.php), [`GeminiTourTranslator.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Services/GeminiTourTranslator.php) |
| **15. Content Revisions & Scheduling** | Blog scheduling via `PublishScheduledBlogPostJob` delayed dispatch. | **Partial** (No `content_revisions` table or revision comparison/restore UI for Blog Posts or Static Pages; Static Pages lack scheduling; Blog scheduling relies only on delayed queue job without a periodic scheduler sweep in `routes/console.php`) | [`PublishScheduledBlogPostJob.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Jobs/PublishScheduledBlogPostJob.php), [`console.php`](file:///f:/Travel%20Website/bookly%20travel/backend/routes/console.php) |
| **16. Global Search** | Basic Filament `$recordTitleAttribute` on some resources. | **Partial** (Missing permission-aware multi-attribute global search across Travelers, Partners, Tours, Bookings, Reviews, Blog Posts, Static Pages, Media Assets, and Admin Users) | — |
| **17. Notifications & Operational Monitoring** | `notifications` table (for partners), `jobs` & `failed_jobs` tables, `NotifyAdminOnPaymentFailure` listener, `AdminDeliveryFailureAlertTest`. | **Partial** (No dedicated Filament Operational Monitoring / Notifications center surfacing failed jobs, failed deliveries, payment reconciliation warnings, failed translation jobs, or media/storage errors) | [`2026_06_02_100002_create_notifications_table.php`](file:///f:/Travel%20Website/bookly%20travel/backend/database/migrations/2026_06_02_100002_create_notifications_table.php) |
| **18. Audit Logs & Governance** | `GovernanceAuditLog` (append-only, immutable Eloquent hooks), `GovernanceAuditService`, `GovernanceAuditResource`, `AuthAuditLog`, `AuditLogResource`, `BookingAuditLog`, `ReviewAuditTrail`. | **Implemented (Needs Expansion)** (Must wire new Admin User, Role/Permission, Traveler, Media Library, File Manager, Revision, and Translation actions into `GovernanceAuditService` and capture request context) | [`GovernanceAuditLog.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/GovernanceAuditLog.php), [`GovernanceAuditService.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/GovernanceAuditService.php) |

---

## 3. Existing Reusable Components & Services

1. **Governance Audit Infrastructure:**
   - [`GovernanceAuditService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/GovernanceAuditService.php) and [`GovernanceAuditLog`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/GovernanceAuditLog.php) provide tamper-evident, append-only audit logging with `actor_type`, `actor_id`, `action`, `target_type`, `target_id`, `before_state`, `after_state`, and `metadata`.
2. **Permission Service & Middleware:**
   - [`AdminAuthorizationService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Services/AdminAuthorizationService.php) and `AdminPermissionMiddleware` (`admin.can`) provide a centralized authorization boundary that can be extended with role templates and granular capability flags without replacing the existing `admin_permissions` architecture.
3. **AI Translation Provider Contract:**
   - [`TourContentTranslator`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Contracts/TourContentTranslator.php) and [`GeminiTourTranslator`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Services/GeminiTourTranslator.php) already implement structured JSON key-path translation against Gemini with transient vs. permanent exception classification (`TransientTranslationException`, `PermanentTranslationException`) and SHA-256 source revision hashing (`TourTranslationService`).
4. **HMAC Preview Token Service:**
   - [`PreviewTokenService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Services/PreviewTokenService.php) implements anti-slug-rebinding, timing-safe HMAC-SHA256 preview tokens with 30-minute expiration, reusable for Static Pages and CMS previews.
5. **Financial Ledger & Payment Models:**
   - [`FinancialLedgerEntry`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/FinancialLedgerEntry.php) (with `updating`/`deleting` blocked at model level), [`Payment`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Models/Payment.php), and [`LedgerService`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Payment/Services/LedgerService.php).
6. **Spatie Settings Integration:**
   - `GeneralSettings`, `SeoSettings`, `ContactSettings`, `BookingSettings`, and `UpdateSettingsAction` with governance audit logging.

---

## 4. Existing Database Structures

Key tables already present in `backend/database/migrations/`:
- **Users & Auth:** `users`, `personal_access_tokens`, `guest_identities`, `auth_audit_logs`, `admin_permissions`.
- **Partners:** `partners`, `partner_profiles`, `partner_settings`, `partner_invitations`, `notifications`.
- **Tours & Inventory:** `categories`, `tours`, `tour_translations`, `tour_translation_states`, `tour_drafts`, `tour_media`, `pricing_tiers`, `availability_rules`, `availability_exceptions`.
- **Bookings & Vouchers:** `bookings`, `booking_audit_logs`, `wishlists`.
- **Payments & Finance:** `payments`, `financial_ledger_entries`, `stripe_webhook_events`.
- **Reviews:** `reviews`, `review_audit_trails`, `review_responses`.
- **CMS & Blog:** `static_pages`, `blog_categories`, `blog_posts`, `author_profiles`, `blog_post_tours`.
- **Governance & Platform:** `governance_audit_logs`, `settings`, `jobs`, `failed_jobs`, `cache`.

---

## 4A. Existing Translation Architecture Audit (Mandatory Deep Dive)

### 4A.1 Existing Translation Storage & Models by Content Type

| Content Type | Backing Table(s) | Translatable Fields | Current Storage Format | Current Translation Mechanism |
|---|---|---|---|---|
| **Blog Posts** (`BlogPost`) | `blog_posts` | `title`, `excerpt`, `body`, `meta_title`, `meta_description` | JSONB columns keyed by locale (`{"en": "...", "es": "...", "it": "..."}`) | Manual form entry only in `BlogPostResource`. No automatic AI translation or field-level state tracking. |
| **Static Pages** (`StaticPage`) | `static_pages` | `title`, `body`, `meta_description` (plus `meta_title` to be added) | JSONB columns keyed by locale (`{"en": "...", "es": "...", "it": "..."}`) | Manual form entry only in `StaticPageResource`. No automatic AI translation or field-level state tracking. |
| **Tours** (`Tour`) | `tour_translations` + `tour_translation_states` | `title`, `description`, `highlights`, `inclusions`, `exclusions`, `meeting_point`, `cancellation_policy`, `itinerary`, `important_information` | Separate rows per `(tour_id, locale)` in `tour_translations`; record-level `source_hash`, `translated_hash`, `status` (`pending`, `ready`, `stale`, `failed`) in `tour_translation_states` | Automatic record-level translation via `TourTranslationService` + `GenerateTourTranslationJob` + `GeminiTourTranslator`. Re-translates all fields when any EN field changes; does not track field-level state or manual edits. |
| **Blog Categories & Tour Categories** | `blog_categories`, `categories` | `name`, `description` (BlogCategory) / `title`, `description` (Category) | JSONB columns keyed by locale (`en`, `es`, `it`) | Manual form entry in Filament resources. |

### 4A.2 Discovered Defects in Existing Translation Handling
1. **`EditBlogPost` Form Hydration Corrupts Untranslated ES/IT Fields with English Text (High):**
   - In [`EditBlogPost.php:49-55`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Filament/Resources/BlogPostResource/Pages/EditBlogPost.php#L49-L55), `mutateFormDataBeforeFill()` calls `$record->contentFor($field, $loc)` instead of reading `$record->{$field}[$loc] ?? ''` directly. Because `BlogPost::contentFor()` falls back to `'en'` when `$loc` is empty, opening the Edit form on an English-only post populates the Spanish (`_es`) and Italian (`_it`) inputs with English copy and persists that English copy as Spanish/Italian on save.
2. **Partial Blog Translations Exposed Publicly Without Warning (Medium):**
   - In [`BlogPostDetailTransformer.php:120-126`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Transformers/BlogPostDetailTransformer.php#L120-L126), `hasLocaleContent()` returns `true` when `! empty($titles[$locale]) || ! empty($bodies[$locale])`. If only `title['es']` exists while `body['es']` is missing, the public API serves a hybrid Spanish-title / English-body page without flagging it as incomplete.
3. **Lack of Field-Level Granularity & Manual Edit Protection:**
   - Updating an English `meta_description` on a Tour currently invalidates the entire record hash and re-translates the full description, highlights, and multi-day itinerary. Furthermore, manual edits to ES/IT fields are not distinguished from AI-generated values (`origin: manual` vs `ai`) and are overwritten on the next source change.

---

## 5. Missing Features Inventory (Gap Analysis vs. Admin 2.0 Scope)

1. **Information Architecture (Section 4):**
   - Sidebar uses legacy groups (`Demo Content`, `Content`, `Platform`, `Operations`, `Monitoring`) instead of the 6-group Admin 2.0 hierarchy (`Dashboard`, `Marketplace Management`, `Finance`, `Content Management`, `Media & Files`, `Administration`).
2. **Dashboard & Analytics (Section 5):**
   - Missing date-range filtering (`Today`, `Yesterday`, `7d`, `30d`, `90d`, `Custom`).
   - Missing full metric set (confirmed/completed/cancelled bookings, gross booking value, refund totals, total travelers, total tours, hidden/flagged reviews).
   - Missing charts (Bookings over time, Revenue over time, Booking status distribution, Top tours, Top partners, Traveler growth, Partner growth, Tour publishing activity).
3. **Admin Users & RBAC (Section 6):**
   - Missing `AdminUserResource` (view, create, edit, activate/deactivate, suspend, revoke sessions/tokens, activity review).
   - Missing Role templates (`Super Admin`, `Operations Manager`, `Content Manager`, `Finance Manager`, `Support Agent`, `Moderator`, `Analyst`) and granular action permissions (`view`, `create`, `edit`, `delete`, `approve`, `reject`, `publish`, `unpublish`, `export`, `manage_permissions`, `manage_settings`).
   - Missing privilege-escalation guard (preventing admins from granting flags they do not possess) and last-active-Super-Admin protection.
4. **Traveler Management (Section 7):**
   - Missing `TravelerResource` listing travelers (`role = 'traveler'`) with booking count, total booking value, account status, detail infolist (profile, booking history, payment summaries, reviews, notifications, admin activity), and suspend/reactivate actions.
5. **Partner, Tour, Booking, and Review Upgrades (Sections 8–11):**
   - Partner detail view missing booking/review performance aggregates and activity history.
   - Tour moderation missing explicit `Archive` action in Filament, booking performance metrics, and HugeRTE rich text fields.
   - Booking resource missing authorized CSV/record export, voucher status inspection, and notification log section.
   - Review resource missing admin `Flag` action and moderation resolution workflow.
6. **Financial Management UI (Section 12):**
   - Missing Financial Overview page/widgets, `PaymentResource` (Transaction Management), `FinancialLedgerResource` (read-only ledger table with filters and authorized export), and Reconciliation Reports.
7. **Media Library & R2 Storage Infrastructure (Section 13):**
   - Missing R2 storage service, `media_assets`, `media_folders`, `media_tags`, and `media_usages` tables, image dimension/variant processing job, Media Library Filament interface (grid/list, upload, metadata editing, usage tracking, safe deletion), and reusable Media Picker component.
8. **File Manager & Storage Analytics (Section 14):**
   - Missing File Manager Filament page for R2 managed storage (folder tree, breadcrumbs, move/rename/copy/soft-delete/restore, storage analytics widget/page, and granular file manager permissions).
9. **HugeRTE Rich Text Editor & HTML Sanitization (Section 16):**
   - Missing self-hosted HugeRTE Filament form component with configurable toolbar presets, Media Library picker integration, and server-side allowlist HTML sanitization (`HtmlSanitizerService`).
10. **CMS, Static Pages & Blog Upgrades (Sections 15, 17, 18):**
    - Static Pages missing create page, `meta_title`, `og_image_url`, `featured_image_url`, `scheduled_at`, preview token support, and public API endpoint (`GET /api/public/pages/{slug}`) + Next.js dynamic integration.
    - Blog missing `blog_tags` (`blog_post_tags`), `AuthorProfileResource`, and scheduler command to trigger due `scheduled_at` posts reliably.
11. **Automatic Field-Level AI Translation & Health Monitoring (Section 19 & Translation Engine Addendum):**
    - Missing automatic field-level translation triggers on English save (translating only created/changed/missing fields into ES & IT without manual button clicks).
    - Missing field-level state & origin tracking (`missing`, `pending`, `processing`, `completed`, `outdated`, `failed`, `manually_edited` + origin `ai` vs `manual` + per-field `source_hash` / `translated_source_hash`).
    - Missing `TranslationHealthCheckService` (evaluating actual stored field values and computing language-level statuses: `complete`, `incomplete`, `processing`, `outdated`, `failed`, `not_started`).
    - Missing Translation Status panels & field-level badges inside Blog, Tour, and Static Page editors, plus manual recovery actions (generate missing, retry failed, regenerate outdated, regenerate selected fields/language).
    - Missing administrative `TranslationHealthDashboard` with global metrics, filters, completion %, and rate-limited bulk actions.
    - Missing public exposure guard ensuring incomplete target-language translations fall back cleanly to English rather than rendering partial translations.
12. **Content Revisions & Safe Editing (Section 20):**
    - Missing `content_revisions` table, version snapshots on save, revision history viewer, diff comparison, restore-as-new-version action, and optimistic locking / concurrent-edit protection.
13. **Global Search & Operational Monitoring (Sections 21–22):**
    - Missing permission-aware Global Search across all 9 entity types and Operational Monitoring center (failed jobs, failed notification deliveries, failed translations, media processing failures, payment reconciliation warnings).

---

## 6. Technical Debt, Security Findings & Duplicate Functionality

### 6.1 Security Findings
1. **Stored XSS Risk in Blog & Static Pages (High):**
   - [`BlogDetail.tsx:86`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/components/blog/BlogDetail.tsx#L86) renders `post.body` via `dangerouslySetInnerHTML={{ __html: post.body }}`, while [`UpdateBlogPostAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php) and [`UpdateStaticPageAction.php`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Actions/UpdateStaticPageAction.php) persist raw HTML without server-side sanitization.
   - **Remediation:** Enforce server-side allowlist HTML sanitization (`HtmlSanitizerService` backed by `symfony/html-sanitizer`) before saving or rendering rich text content.
2. **Mock Upload Presigned URL Stub (Medium):**
   - [`UploadController.php:33`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Partner/Controllers/UploadController.php#L33) returns a hardcoded `https://r2.bookly.test/...` URL instead of using a unified, validated R2 storage service.
3. **Financial Ledger Permission Sharing (Low/Medium):**
   - `GET /api/admin/financial-ledger` currently shares `view_all_analytics` rather than a dedicated financial permission (`view_finance` / `view_financial_ledger`).
4. **Missing Privilege Escalation & Last-Super-Admin Guards (Medium):**
   - Currently no admin user management UI exists; introducing one requires strict server-side checks so an admin cannot grant permissions they do not hold and cannot deactivate/demote the last active Super Admin.

### 6.2 Technical Debt & Duplicate Functionality
1. **Static Pages Hardcoded in Next.js vs. Database `StaticPage` Model:**
   - [`StaticPage`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Admin/Models/StaticPage.php) exists in the backend and Filament (`/admin/static-pages`), but Next.js [`privacy/page.tsx`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/app/[locale]/(public)/privacy/page.tsx) and [`terms/page.tsx`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/app/[locale]/(public)/terms/page.tsx) read static keys from `messages/*.json` (`next-intl`) with no `/api/public/pages/{slug}` endpoint connecting them.
2. **Orphaned Next.js Admin Component:**
   - [`frontend/src/components/admin/AdminReviewPanel.tsx`](file:///f:/Travel%20Website/bookly%20travel/frontend/src/components/admin/AdminReviewPanel.tsx) is an unused React component from an early spec; Filament `ReviewResource` is the official admin surface per the Bookly Constitution.
3. **Blog Scheduled Publication Reliability:**
   - [`UpdateBlogPostAction.php:170`](file:///f:/Travel%20Website/bookly%20travel/backend/app/Domains/Blog/Actions/UpdateBlogPostAction.php#L170) dispatches a delayed queue job (`PublishScheduledBlogPostJob::dispatch($post->id)->delay($post->scheduled_at)`), which can be lost if Redis is flushed or if `scheduled_at` is edited multiple times. A scheduled command in `routes/console.php` should also sweep due scheduled posts and static pages every minute idempotently.

---

## 7. Integration Dependencies & Migration Risks

1. **Cloudflare R2 & Local/Test Compatibility:**
   - Production uses Cloudflare R2 (S3-compatible API). Local Docker (`bookly-backend`) and CI test environments (`pest`) do not have live R2 credentials.
   - **Mitigation:** Configure a dedicated `r2` filesystem disk in `config/filesystems.php` that uses S3/R2 when `R2_BUCKET` / `AWS_BUCKET` credentials are configured and gracefully falls back to a local managed disk (`storage/app/public/r2`) in local/testing environments, while keeping all operations behind a unified `MediaStorageService`.
2. **Existing Data Preservation:**
   - `blog_posts`, `blog_categories`, `static_pages`, `tours`, `tour_media`, and `admin_permissions` already contain data and active tests.
   - **Mitigation:** All schema changes must be strictly additive (new tables and nullable columns), preserving existing JSONB columns (`title`, `body`, `excerpt`, `meta_title`, `meta_description`) and existing `admin_permissions.flags` keys so all 168 existing admin tests continue to pass without modification.
3. **Dirty Working Tree on Current Branch:**
   - `git status` shows modified and untracked files from `021-homepage-discovery` and `CategoryResource` on `fix/master-admin-audit-remediation`.
   - **Mitigation:** Must not reset or overwrite uncommitted user work when creating/switching to `feature/admin-2.0`.

---

## 8. Recommended Implementation Sequence

Following the Spec Kit workflow (`/speckit.specify` → `/speckit.clarify` → `/speckit.plan` → `/speckit.tasks` → phased implementation):

1. **Phase A — Foundation, RBAC & Navigation:**
   - Additive migrations for `admin_roles`, `users` status/suspension columns, and extended `AdminAuthorizationService` permissions (preserving all 10 existing flags).
   - 6-group permission-aware navigation in `AdminPanelProvider`.
   - `AdminUserResource` & `AdminRoleResource` with privilege-escalation prevention, last-Super-Admin protection, session/token revocation, and `GovernanceAuditService` logging.
2. **Phase B — Core Marketplace Administration:**
   - Upgraded Dashboard with date-range filter (`Today`, `Yesterday`, `7d`, `30d`, `90d`, `Custom`), cached KPI aggregates, and 8 analytics chart/table widgets.
   - `TravelerResource` (list, detail infolist, suspend/reactivate, booking/review inspection).
   - Upgraded `PartnerResource`, `TourResource` (archive action, booking stats, audit trail), `DestinationResource` / destination management, `BookingResource` (export, voucher/refund/notification details), and `ReviewResource` (flag/resolve actions).
3. **Phase C — Financial Administration:**
   - Financial Overview page/widgets, `PaymentResource` (Transaction Management), read-only `FinancialLedgerResource`, and Reconciliation Report view/export.
4. **Phase D & E — Media Infrastructure & Media Library:**
   - Additive migrations for `media_folders`, `media_assets`, `media_tags`, `media_asset_tag`, and `media_usages`.
   - Shared `MediaStorageService` (R2 + local/test driver compatibility), MIME/dimension/security validation, async `ProcessMediaAssetJob`, usage tracker, `MediaAssetResource` (grid/list view, metadata editor, bulk actions, safe delete/restore), and reusable Media Picker modal.
5. **Phase F — File Manager & Storage Analytics:**
   - Filament `FileManager` page operating strictly on Bookly's managed storage disk (folder tree, breadcrumbs, upload/download/rename/move/copy/soft-delete/restore) and `StorageAnalytics` page/widgets.
6. **Phase G — CMS, HugeRTE, Static Pages, Blog, Revisions & Scheduling:**
   - Self-hosted HugeRTE Filament field component with configurable toolbars and Media Picker integration.
   - Server-side `HtmlSanitizerService` (using `symfony/html-sanitizer` allowlist).
   - `content_revisions` table, `ContentRevisionService`, revision history/diff/restore UI, and optimistic lock version checks.
   - Upgraded `StaticPageResource` (create, scheduling, preview, SEO/OG fields) + `GET /api/public/pages/{slug}` + Next.js fallback-safe rendering.
   - Upgraded `BlogPostResource`, `BlogTagResource`, `AuthorProfileResource`, and idempotent scheduler command for scheduled posts and static pages.
7. **Phase H — Automatic Field-Level AI Translation & Translation Health Monitoring:**
   - Additive `translation_field_states` (and `translation_logs` / `translation_batches`) migration preserving existing `blog_posts`, `static_pages`, and `tour_translations` storage.
   - Provider-agnostic `ContentTranslatorContract` & `GeminiContentTranslator` supporting HTML structure preservation, server-side HTML sanitization, SEO length validation, and non-identical/UTF-8 output verification.
   - Automatic field-level diff & dispatch on English save (`AutoTranslationDispatcher`), idempotent Redis job (`TranslateContentFieldJob`) with exponential backoff, manual-edit preservation (`manually_edited` origin protection), and `TranslationHealthCheckService`.
   - Editor Translation Status panels, field-level badges, recovery actions, and the global `TranslationHealthDashboard` Filament page with batched bulk actions and grouped failure notifications.
8. **Phase I — Global Search & Operational Monitoring:**
   - Permission-aware Global Search across all 9 entity types.
   - Operational Monitoring & Notifications center (`OperationalAlertsPage` / widgets for failed jobs, failed notifications, failed translations, media errors, and reconciliation warnings).
9. **Phase J — Complete Automated Regression, E2E & Documentation:**
   - Pest backend/Filament/security tests, Playwright E2E admin workflows, and all 8 documentation deliverables in `docs/reports/`.

