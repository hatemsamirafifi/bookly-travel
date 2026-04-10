<!--
  ============================================================
  SYNC IMPACT REPORT
  ============================================================
  Version change : 0.0.0 (template) → 1.0.0
  Bump rationale : MAJOR — first concrete ratification of all
                   governing principles; no prior version existed.

  Modified principles:
    - [PRINCIPLE_1_NAME] → I. Marketplace-First
    - [PRINCIPLE_2_NAME] → II. Tours-Only Discipline
    - [PRINCIPLE_3_NAME] → III. Direct Booking Only
    - [PRINCIPLE_4_NAME] → IV. Admin-Governed Publishing
    - [PRINCIPLE_5_NAME] → V. Platform-Controlled Commerce

  Added sections:
    - VI. Completed-Booking Review Integrity (new principle)
    - Architecture & Stack (Section 2)
    - Security Principles (Section 3)
    - Public Experience & SEO Rules (Section 4)
    - Code Structure & Engineering Boundaries (Section 5)
    - Queueing & Async Work Policy (Section 6)
    - Audit Logging & Operational Governance (Section 7)
    - Testing & Quality Standards (Section 8)
    - Governance (filled from template)

  Removed sections: none

  Templates requiring updates:
    ✅ plan-template.md   — Constitution Check section is
       generic; no update needed (gates derived at plan time).
    ✅ spec-template.md   — No constitution-specific tokens;
       compatible as-is.
    ✅ tasks-template.md  — No constitution-specific tokens;
       compatible as-is.

  Follow-up TODOs: none
  ============================================================
-->

# Bookly Constitution

## Core Principles

### I. Marketplace-First

Bookly MUST be built as a real multi-vendor marketplace where
**partners** control their listings but all sales are processed
through the platform.

- Every tour listing MUST be owned by a registered partner.
- The platform MUST mediate all transactions between travelers
  and partners.
- Partners MUST NOT transact directly with travelers outside
  the platform for any booking initiated on Bookly.

### II. Tours-Only Discipline

The product scope is **strictly limited** to tours and related
activities. No hotels, flights, transfers, or unrelated verticals
are permitted unless explicitly amended in a future constitution
revision.

- All features, data models, and UI surfaces MUST serve the
  tours vertical exclusively.
- Any scope-expansion request MUST go through a formal
  constitution amendment before work begins.

### III. Direct Booking Only

All tours MUST support **instant booking** with **direct
confirmation**. Inquiry-based, request-based, or manual-approval
booking flows are prohibited in Phase 1.

- The booking endpoint MUST return a confirmed booking
  synchronously (or via a deterministic async confirmation
  within seconds).
- No "request to book" or "waitlist" patterns are allowed
  without a constitution amendment.

### IV. Admin-Governed Publishing

Partners MAY create and manage tours, pricing, and availability,
but **admin approval** is required before any tour is visible to
the public.

- Tour status MUST include at minimum: `draft`, `pending_review`,
  `published`, `rejected`, `archived`.
- Only tours in `published` status MUST appear in public search
  and discovery.
- Admins MUST have tooling to approve, reject (with reason),
  and unpublish tours.

### V. Platform-Controlled Commerce

All payments MUST be processed through the platform. Revenue
movement MUST be tracked with **financial auditability**.

- Partners MUST NOT collect payments directly from travelers
  for platform bookings.
- Every financial transaction MUST produce an immutable ledger
  entry.
- Stripe is the initial payment gateway; the architecture MUST
  allow future gateway extensibility.

### VI. Completed-Booking Review Integrity

Reviews MUST only be submitted for **completed bookings**.

- The system MUST verify that the associated booking has a
  `completed` status before accepting a review submission.
- One review per booking per traveler is the maximum.
- Partners MAY respond to reviews; admin MAY moderate.

## Architecture & Approved Stack

### API-First

The platform MUST be API-first. All surfaces (public website,
partner dashboard, admin dashboard) MUST consume the Laravel
backend exclusively via APIs. No server-rendered HTML from the
backend is permitted for application views.

### Approved Core Stack

| Layer              | Technology                              |
|--------------------|-----------------------------------------|
| Frontend           | Next.js 14, TypeScript, Tailwind CSS    |
| Backend            | Laravel (API-only)                      |
| Authentication     | Laravel Sanctum                         |
| Database           | PostgreSQL                              |
| Cache & Queue      | Redis                                   |
| Search             | Laravel Scout (Meilisearch later)       |
| Object Storage     | Cloudflare R2                           |
| CDN / Edge         | Cloudflare                              |
| Web Server         | Nginx                                   |
| Payments           | Stripe (extensible for future gateways) |

Any deviation from this stack MUST be justified in a Complexity
Tracking table and approved before implementation.

### Shared Backend, Separated Access Domains

Admin and Partner dashboards MUST consume the same backend but
access is separated by:

- Route groups (e.g., `/api/admin/*`, `/api/partner/*`)
- Middleware
- Role-based permissions
- Ownership policies (partners see only their own resources)

### Modular Service Boundaries

The backend MUST be organized into explicit business domains:

Auth · Partners · Tours · Pricing · Availability · Bookings ·
Payments · Reviews · Finance · Notifications · Translation ·
Admin Operations

Each domain MUST have clear service boundaries; cross-domain
calls MUST go through documented service interfaces, not direct
model access.

### Search & Performance Separation

Public discovery MUST NOT rely on direct SQL filtering against
the primary relational database for search-heavy queries. Search
MUST be abstracted behind a search index layer (Laravel Scout).

## Security Principles

### Security-First Mandate

All features, workflows, and infrastructure choices MUST be
evaluated with a security-first mindset. Security is never
deferred to "later."

### Mandatory Input Validation

All write operations MUST be validated server-side using Laravel
Form Requests or equivalent. Client-side validation MAY assist
the user experience but MUST NOT be relied upon for security.

### Strict Authorization

Every protected resource MUST enforce:

1. Authentication (is the user logged in?)
2. Role check (does the user hold the required role?)
3. Permission check (does the role grant this action?)
4. Ownership check (does the user own or have access to this
   specific resource?)

### Secrets Handling

Secrets (API keys, database credentials, encryption keys) MUST
NOT be hardcoded or committed to source control. All secrets
MUST be loaded from environment variables or a secrets manager.

### Idempotent Financial Flows

All payment and booking workflows MUST be idempotent. Retries
MUST NOT create duplicate charges, duplicate bookings, or
inconsistent ledger entries. Idempotency keys MUST be used
where applicable.

### Secure File Handling

File uploads MUST be validated for:

- Allowed MIME types
- Maximum file size
- File integrity (no path traversal, no executable content)
- Correct storage paths (Cloudflare R2 with scoped keys)

## Public Experience & SEO Rules

### SEO-First

All public-facing pages MUST be designed with SEO best practices
to ensure discoverability by search engines.

### Public Rendering Expectations

Public pages MUST support:

- Indexable routes with proper metadata (title, description,
  Open Graph tags)
- Localized routes and content (multi-language support planned)
- Canonical tags to avoid duplicate content
- Sitemap generation (XML)
- Fast performance (target Lighthouse score ≥ 90 on Performance)

Next.js SSR/SSG MUST be used for public pages to guarantee
server-rendered, crawlable HTML.

## Code Structure & Engineering Boundaries

### Thin Controllers

Controllers MUST manage requests, authorization, and responses
only. Controllers MUST NOT contain business logic, complex
conditional flows, or direct data-transformation code.

### No Direct DB Access from Controllers

Controllers MUST NOT execute database queries directly. All
data access MUST go through services, repositories, or action
classes.

### Business Logic in Services/Actions

Business logic (pricing calculations, booking flow orchestration,
payment reconciliation, availability checks) MUST be implemented
in dedicated service or action classes, never in controllers,
models, or middleware.

## Queueing & Async Work Policy

### Long-Running Jobs MUST Be Queued

Non-blocking tasks MUST be dispatched to the queue (Redis).
Examples include:

- Search index updates
- Notification delivery (email, SMS, push)
- Report generation
- Image processing / thumbnail generation

### Retry-Safety

All queued jobs MUST be retry-safe (idempotent). A job that
fails and is retried MUST NOT produce duplicate side effects
(e.g., duplicate emails, duplicate index entries).

## Audit Logging & Operational Governance

### Mandatory Audit Logs

Audit logs are required for:

- Admin actions (approve / reject tour, ban partner, etc.)
- Financial transactions (charges, refunds, payouts)
- Booking status changes (created → confirmed → completed →
  cancelled)
- Partner management actions (onboarding, suspension, tier
  changes)

Each audit log entry MUST capture: actor, action, target
resource, timestamp, and before/after state where applicable.

## Testing & Quality Standards

### Minimum Testing Coverage

Critical business flows MUST have automated tests before they
are considered complete. At minimum:

- Booking creation and confirmation flow
- Payment charge and refund flow
- Tour publishing approval workflow
- Authentication and authorization gates

Test types MUST include integration tests for end-to-end flows
and unit tests for isolated service logic.

## Governance

This constitution supersedes all other engineering practices,
ad-hoc decisions, and tribal knowledge for the Bookly project.

### Amendment Procedure

1. Propose the amendment in writing with rationale.
2. Evaluate impact on existing specifications, plans, and tasks.
3. Obtain explicit approval from the project owner.
4. Update this constitution with a version bump following
   semantic versioning (see below).
5. Propagate changes to all dependent templates and active
   specs.

### Versioning Policy

- **MAJOR**: Backward-incompatible principle removals or
  redefinitions.
- **MINOR**: New principle or section added, or materially
  expanded guidance.
- **PATCH**: Clarifications, wording fixes, non-semantic
  refinements.

### Compliance Review

- All specifications MUST reference this constitution version.
- All implementation plans MUST include a Constitution Check
  gate verifying alignment with active principles.
- All pull requests / code reviews MUST verify compliance with
  applicable principles before merge.

### Out-of-Scope for Phase 1

The following are explicitly out of scope and MUST NOT be
implemented unless this constitution is amended:

- Hotels, flights, and transfers integration
- Third-party OAuth via Laravel Passport
- Native mobile applications
- Automated partner payouts

### Application Surfaces

This constitution governs all surfaces of the Bookly platform:

- Public traveler-facing website
- Partner dashboard
- Admin dashboard
- Shared backend services (Laravel API)
- Documentation and delivery workflows

**Version**: 1.0.0 | **Ratified**: 2026-04-10 | **Last Amended**: 2026-04-10
