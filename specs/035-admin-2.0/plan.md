# Implementation Plan: Bookly Admin 2.0 (Spec 035)

- **Spec:** [`specs/035-admin-2.0/spec.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/spec.md)
- **Data Model:** [`specs/035-admin-2.0/data-model.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/data-model.md)
- **Tasks:** [`specs/035-admin-2.0/tasks.md`](file:///f:/Travel%20Website/bookly%20travel/specs/035-admin-2.0/tasks.md)
- **Architecture Report:** [`docs/reports/admin-2.0-architecture.md`](file:///f:/Travel%20Website/bookly%20travel/docs/reports/admin-2.0-architecture.md)
- **Database Impact Report:** [`docs/reports/admin-2.0-database-impact.md`](file:///f:/Travel%20Website/bookly%20travel/docs/reports/admin-2.0-database-impact.md)
- **Security Plan:** [`docs/reports/admin-2.0-security-review.md`](file:///f:/Travel%20Website/bookly%20travel/docs/reports/admin-2.0-security-review.md)
- **Governing Constitution:** [Bookly Constitution v2.1.0](file:///f:/Travel%20Website/bookly%20travel/.specify/memory/constitution.md)

---

## 1. Constitution Check (v2.1.0)

| Principle | Compliance | Verification Notes |
|---|---|---|
| **I. Marketplace Integrity & Tours-Only Scope** | **PASS** | All admin capabilities govern Bookly's multi-partner tours marketplace, content, media, and operations. |
| **II. Booking, Publishing & Review Integrity** | **PASS** | Preserves tour approval rules, partner approval prerequisite, completed-booking review eligibility, and direct-confirmation booking state machine. |
| **III. Platform-Controlled & Auditable Commerce** | **PASS** | Financial overview, payments, and ledger UI are strictly read-only over integer minor-unit records; never bypasses Stripe or fabricates balances. |
| **IV. API-First Boundaries & Strict Authorization** | **PASS** | Filament remains restricted to `/admin`; public Static Pages and Blog consume `/api/public/*`; business logic resides in Domain Actions/Services; every route/action enforces server-side policies. |
| **V. Bookly Design System & Originality** | **PASS** | Preserves Bookly Navy (`#0A2540`), Gold (`#FFB800`), `Plus Jakarta Sans`, and Filament design system tokens. |
| **VI. Accessibility, Localization & Responsive** | **PASS** | Targets WCAG 2.1 AA; maintains `en`/`es`/`it` localization and clean English fallback when derived translations are incomplete. |
| **VII. Evidence-Based Quality & Regression Safety** | **PASS** | Every phase requires Pest unit/feature/Filament/security tests and Playwright E2E coverage before completion. |

---

## 2. Rollback & Migration Safety Plan

1. All new migrations (`admin_roles`, `media_folders`, `media_assets`, `media_tags`, `media_usages`, `blog_tags`, `content_revisions`, `translation_field_states`, and additive columns on `users`, `blog_posts`, `static_pages`) are strictly additive and reversible via `php artisan migrate:rollback`.
2. Next.js public Static Pages (`/privacy`, `/terms`, and dynamic pages) fall back gracefully to existing `next-intl` translations if a database `StaticPage` record is not yet published, ensuring zero public downtime during deployment or rollback.
3. Existing `AdminPermission` flags (`manage_tours`, `manage_partners`, `manage_bookings`, `moderate_reviews`, `view_all_analytics`, `manage_users`, `manage_settings`, `manage_cms`, `manage_blog`, `view_audit_log`) remain 100% backward-compatible.

