# Feature Specification: Notifications and Vouchers

**Feature Branch**: `014-notifications-vouchers`
**Created**: 2026-07-04
**Status**: Draft
**Input**: User description: "Phase 6 — Notifications and Vouchers (Spec 014)."
**Plan Reference**: Frontend Implementation Plan, Phase 6 — Notifications and Vouchers (Spec `014`)
**Constitution**: Bookly Constitution v1.1.0 (Principles V, VI; Sections: Queueing & Async Work Policy, Audit Logging & Operational Governance, Strict Authorization, Idempotent Financial Flows; Out-of-Scope §1 — automated partner payouts)

## Clarifications

### Session 2026-07-04

Informed decisions recorded as defaults (no [NEEDS CLARIFICATION] markers remain):

- **Q**: Traveler in-app notification center (like the partner one) vs. email-only? → **A**: Phase 1 is **email-only for travelers**. The partner in-app notification center already exists and is in scope here only for completion/polish; no traveler in-app notification center is built in this spec. Travelers receive transactional emails and download vouchers from their booking detail page.
- **Q**: Can guests (booked without an account) download their voucher from the site? → **A**: **Email-only delivery for guests.** The dashboard voucher download is auth-gated for the booking owner; guests receive their voucher PDF by email at confirmation time. A token-based guest download link is **out of scope** for Phase 1 (documented as a future enhancement, not a gap).
- **Q**: Which booking statuses may download a voucher? → **A**: Any **post-payment, non-cancelled** booking (`confirmed`, `completed`) may download its voucher. Cancelled bookings MUST NOT expose a downloadable voucher. The existing implementation guards on `confirmed`; this spec extends the allowed set to include `completed` so past bookings remain provable.
- **Q**: Can an admin manually re-send a failed notification email? → **A**: Phase 1 provides **automatic retry + admin alerting only**. A manual "re-send" action in the admin panel is **deferred**; the failed-delivery alert lets an admin investigate and, if needed, re-trigger through back-office tooling in a future spec.
- **Q**: Are partner payout notifications in scope? → **A**: **No.** Automated partner payouts are explicitly out of scope per the constitution (Out-of-Scope §1). Payout-related notifications are excluded; only booking/lifecycle/approval notifications are in scope.
- **Q**: What does the voucher QR code encode — the bare booking reference or a scannable verification URL? → **A**: **Option B — a public verification URL.** The QR MUST encode the public web URL `https://bookly.travel/v/{booking_reference}` (the project's public base URL; exact host/route finalized in the plan), NOT the bare reference and NOT a JSON payload. A matching public, read-only, unauthenticated verification surface exposes only: booking reference, verification status, tour title, scheduled tour date, participant count, and optionally booking created date and voucher-generated timestamp. It MUST NEVER expose traveler name, email, phone, payment info, guest identity, internal database IDs, or partner internal notes. Verification states: `VALID`, `CANCELLED`, `PENDING`, `EXPIRED`, and the design MUST naturally support a future `USED` (redeemed) state without changing the QR format. The opaque booking reference is the public lookup key (no numeric IDs); unknown references return 404 and the surface never reveals whether any other booking exists (no enumeration). Per the constitution's API-First rule for public surfaces, the verification surface is split: a read-only Laravel API endpoint returns a `VerificationResult` JSON payload, and a minimal Next.js page at `/v/{booking_reference}` renders it with a large status indicator — no auth, no dashboard, no navigation to private surfaces. The QR encodes the public web URL, which the plan phase maps to the project's actual public base URL and route structure.
- **Q**: How should admin alerts for exhausted delivery failures be surfaced — new in-app Filament surface vs. existing log/Slack channels? → **A**: **Option A — reuse the existing channels.** The system MUST alert admins via the already-implemented `NotifyAdminOnEmailDeliveryFailure` listener: an ERROR-level log entry (always) and a best-effort Slack webhook alert (when `services.slack.admin_webhook_url` is configured). The system MUST NOT introduce a new in-app admin notification surface — no `admin_notifications` table, no admin `Notification` model, no Filament Notification resource, no unread/read state, no admin inbox. Slack is best-effort only and MUST NOT fail the original listener/job if Slack itself fails. Logs and Slack alerts MUST include operational context (booking reference, mail class, exception message, queue/job information where available) and MUST NEVER include sensitive payment information or PII beyond what is needed to locate the booking. US5, FR-012, and FR-019 are worded to match this implementation.

## User Scenarios & Testing *(mandatory)*

This feature covers **transactional communication and proof-of-booking** for the
Bookly marketplace. Primary actors are **Travelers** (receive booking
confirmation + voucher, download voucher) and **Partners** (receive booking &
lifecycle notifications, in-app + email). Secondary actors are **Admins**
(notified of delivery failures for operational follow-up) and **Guests**
(unregistered travelers who book and receive email-only delivery).

### User Story 1 - Booking Confirmation & Voucher Delivery (Priority: P1)

When a traveler's booking is confirmed (payment captured), the traveler
receives a localized confirmation email containing the booking reference, tour
date, participants, and total paid, followed by a voucher PDF attached to a
second localized email. The voucher is the traveler's proof of booking and
entry ticket, containing the booking reference, a QR code, tour details,
meeting point, participants, and total paid. The same confirmation event also
notifies the partner of the new booking.

**Why this priority**: This is the marketplace's core trust artifact — without
a confirmation email and a voucher, travelers have no proof they paid, and
partners do not know a booking exists. It is the constitutional minimum for
platform-controlled commerce (Principle V).

**Independent Test**: A traveler completes a paid booking; within minutes they
receive a localized confirmation email and a voucher email with a PDF
attachment containing the booking reference and QR code; the partner receives a
new-booking notification.

**Acceptance Scenarios**:

1. **Given** a booking whose payment has succeeded, **When** the confirmation
   flow runs, **Then** the traveler receives a localized confirmation email and
   a localized voucher email with a PDF attachment, and the partner receives a
   new-booking notification (email and/or in-app).
2. **Given** the booking's locale is ES or IT, **When** the emails are
   composed, **Then** each email's subject and body render in that locale, with
   EN as the fallback when a localized template is missing.
3. **Given** the voucher PDF, **When** it is opened, **Then** it shows the
   booking reference, a QR code encoding the booking reference, tour title,
   tour date, participants, total paid, and meeting point.
4. **Given** the same booking is confirmed twice (e.g. a retry), **When** the
   confirmation flow runs again, **Then** the traveler receives exactly one
   confirmation email and one voucher email — never duplicates.

---

### User Story 2 - Voucher Download & Authorization (Priority: P1)

A registered traveler can download their voucher PDF at any time from their
booking detail page. Only the booking owner may download; any other user
(including other travelers, unauthenticated visitors, and partners) is denied.
Cancelled bookings expose no downloadable voucher; past completed bookings
remain downloadable as proof.

**Why this priority**: The voucher is a credential. Unauthorized download would
leak another traveler's booking data; missing download would leave paid
travelers without their ticket. Authorization is a constitutional mandate
(Strict Authorization).

**Independent Test**: The booking owner downloads their voucher successfully; a
different authenticated traveler attempting the same URL is refused; a guest
(has no account) cannot use the dashboard download (email-only).

**Acceptance Scenarios**:

1. **Given** the booking owner viewing a confirmed or completed booking,
   **When** they request the voucher download, **Then** a PDF is returned with
   the correct content-type and filename.
2. **Given** an authenticated user who is **not** the booking owner, **When**
   they request that booking's voucher, **Then** they receive a not-found /
   forbidden response and no voucher is served.
3. **Given** an unauthenticated visitor, **When** they request any voucher URL,
   **Then** they are refused (no voucher served).
4. **Given** a cancelled booking, **When** its owner requests the voucher,
   **Then** no voucher is served (the download is unavailable for cancelled
   bookings).
5. **Given** a guest booking (no traveler account), **When** confirmation
   completes, **Then** the voucher is delivered by email only; there is no
   dashboard download path for guests.

---

### User Story 3 - Partner Lifecycle & Booking Notifications (Priority: P1)

Partners receive notifications for the events that affect their business: a new
booking on their tour, a booking cancellation, and partner-lifecycle decisions
(approval, rejection, suspension). Notifications are delivered both in-app (an
unread indicator, a list, mark-as-read) and by email, so a partner can be
reached whether or not they are logged in.

**Why this priority**: Partners are the supply side; they must learn of bookings
and cancellations to fulfill them, and of approval/rejection/suspension to know
their standing. Notification delivery is a constitutional async-work mandate
(Queueing & Async Work Policy).

**Independent Test**: A booking is made on a partner's tour; the partner's
in-app unread count increases, the notification appears in their list, and an
email is sent; marking it read drops the unread count.

**Acceptance Scenarios**:

1. **Given** a new confirmed booking on a partner's tour, **When** the
   confirmation flow runs, **Then** the partner receives an in-app notification
   (unread) and an email notification.
2. **Given** a booking cancellation, **When** the cancellation flow runs,
   **Then** the partner receives an in-app cancellation notification and an
   email.
3. **Given** an admin approves, rejects, or suspends a partner, **When** the
   governance action completes, **Then** the partner receives a localized email
   describing the decision (rejection/suspension includes a reason).
4. **Given** a partner with unread notifications, **When** they open the
   notifications list, **Then** they see the unread items and can mark one or
   all as read, after which the unread count reflects the new total.
5. **Given** a partner viewing only their own notifications, **When** they list
   notifications, **Then** they never see another partner's notifications.

---

### User Story 4 - Delivery Resilience & Failure Handling (Priority: P2)

Notification delivery is asynchronous, retry-safe, and idempotent. A transient
delivery failure triggers bounded retries; if all retries are exhausted, the
failure is logged and an admin alert is raised so an operator can investigate.
Email delivery failures **never** alter the booking's status — a paid booking
stays confirmed even if its confirmation email could not be delivered.

**Why this priority**: Travelers and partners depend on these messages, but
email is an unreliable channel. Resilience protects the platform from duplicate
emails on retry and from silent drop-outs, and protects booking state from
email-side failures (Idempotent Financial Flows, Queueing & Async Work Policy).

**Independent Test**: A confirmation email delivery fails repeatedly; after the
retry budget is exhausted an admin alert is raised; the booking remains
confirmed; no duplicate emails were sent during retries.

**Acceptance Scenarios**:

1. **Given** a queued notification job that fails transiently, **When** it is
   retried, **Then** it is re-attempted up to the configured retry budget without
   producing duplicate emails.
2. **Given** a confirmation email job whose retries are all exhausted, **When**
   the final attempt fails, **Then** a failure event is raised and an admin
   notification is generated; the booking's status is unchanged.
3. **Given** a successful delivery after a transient failure, **When** the job
   ultimately succeeds, **Then** the traveler receives exactly one email and
   the "already sent" guard prevents any future duplicate.
4. **Given** voucher generation fails during an otherwise-successful
   confirmation, **When** the failure is caught, **Then** the confirmation email
   is still delivered and the failure is logged for follow-up (voucher failure
   does not block the confirmation email).

---

### User Story 5 - Admin Visibility of Delivery Failures (Priority: P2)

When a booking confirmation email cannot be delivered after all retries, an
admin is alerted through the project's existing operational channels — an
ERROR-level log entry (always) and, when configured, a best-effort Slack webhook
alert — so the booking can be reconciled manually (e.g. the traveler can be
contacted, or the email address corrected). The alert carries enough
operational context (booking reference, mail class, exception message,
queue/job information) for an operator to locate the booking and act. No in-app
admin notification surface is introduced.

**Why this priority**: Operational governance (Audit Logging & Operational
Governance). Without surfacing failures, a paid booking could sit with an
un-delivered voucher and no one would know.

**Independent Test**: A delivery fails to exhaustion; an ERROR log entry is
written referencing the booking and the failure reason; if Slack is configured,
an alert is posted; the operator can locate the booking from the alert context;
if Slack is not configured, only the log entry is produced.

**Acceptance Scenarios**:

1. **Given** an exhausted delivery failure, **When** the failure event is
   handled, **Then** an ERROR-level log entry is written with the booking
   reference, mail class, exception message, and queue/job information where
   available.
2. **Given** a configured Slack webhook, **When** the failure event is handled,
   **Then** a best-effort Slack alert is posted containing the booking reference;
   if Slack itself fails, the original listener/job is NOT failed because of it.
3. **Given** no Slack webhook configured, **When** the failure event is handled,
   **Then** only the ERROR log entry is produced (no Slack call, no in-app
   notification).
4. **Given** any failure alert (log or Slack), **When** an operator inspects it,
   **Then** it contains the booking reference and failure reason, and never
   contains sensitive payment information or PII beyond what is needed to locate
   the booking.
5. **Given** the requirement to avoid new infrastructure, **When** this story is
   implemented, **Then** no `admin_notifications` table, no admin `Notification`
   model, no Filament Notification resource, and no admin inbox/unread-state are
   introduced — the existing log + optional Slack channels are the official
   admin alert surface.

---

### User Story 6 - Localization of Notifications (Priority: P2)

Every traveler-facing and partner-facing email renders in the booking's locale
(EN, ES, or IT), with EN as the fallback. Subjects, bodies, and the voucher's
language all follow the booking locale. In-app partner notifications carry
locale-appropriate titles and bodies.

**Why this priority**: The marketplace operates in EN/ES/IT; sending an Italian
traveler an English voucher undermines trust and contradicts the
multi-language mandate (Public Experience & SEO Rules — multi-language
support).

**Independent Test**: A booking is made with locale IT; the confirmation email,
voucher email, voucher PDF text, and any partner notifications all render in
Italian.

**Acceptance Scenarios**:

1. **Given** a booking with locale ES, **When** emails are sent, **Then** the
   confirmation subject/body and voucher subject/body render in Spanish.
2. **Given** a booking with locale IT, **When** the voucher PDF is generated,
   **Then** the voucher's labels and content render in Italian.
3. **Given** a booking whose locale has a missing template, **When** the email
   is composed, **Then** it falls back to the English template rather than
   failing.
4. **Given** a partner-facing email, **When** it is sent, **Then** its subject
   and body are localized to the partner's preferred locale where available,
   with EN fallback.

---

### User Story 7 - Voucher Regeneration & Freshness (Priority: P3)

If a confirmed booking's details change in a way that affects the voucher
(tour date, participants), a fresh voucher can be produced on demand so the
traveler always downloads a voucher matching their current booking. Stale
cached vouchers are never served for a booking whose details have changed.

**Why this priority**: Prevents a traveler from presenting a voucher with the
wrong date or participant count. Lower priority because most bookings never
change after confirmation.

**Independent Test**: A booking's participant count is updated after
confirmation; the traveler downloads the voucher and the PDF reflects the new
participant count.

**Acceptance Scenarios**:

1. **Given** a previously generated voucher, **When** the booking's
   date/participants change, **Then** the next download returns a voucher
   matching the current booking details (not the stale cached file).
2. **Given** an unchanged booking, **When** the voucher is requested again,
   **Then** the existing generated voucher is served (no unnecessary
   regeneration).

---

### User Story 8 - Public Voucher Verification (Priority: P1)

Anyone holding a voucher — a partner or ticket-taker at the tour meeting point,
or the traveler themselves — can scan the voucher QR code and land on a public,
read-only verification page that confirms the booking is genuine and shows just
the public details (booking reference, status, tour title, tour date,
participants). No sign-in is required, no private data is exposed, and the page
cannot be used to enumerate or guess other bookings.

**Why this priority**: The voucher's whole purpose is to serve as an entry
ticket; without a verifiable QR, a screenshot can be forged and a partner
cannot confidently admit a traveler. Scan-and-verify is the trust mechanism that
makes the voucher meaningful (Principle V — platform-controlled commerce).

**Independent Test**: A booking is confirmed and its voucher generated; the QR
is scanned; the verification page loads without sign-in and shows VALID with the
tour title and date; an unknown reference returns 404; a cancelled booking shows
CANCELLED.

**Acceptance Scenarios**:

1. **Given** a confirmed booking's voucher QR, **When** it is scanned/opened,
   **Then** the public verification page loads (no auth) and shows VALID with
   booking reference, tour title, tour date, and participant count.
2. **Given** a cancelled booking's reference, **When** the verification endpoint
   is called, **Then** the page shows CANCELLED and never offers a voucher
   download from this surface.
3. **Given** a pending (awaiting payment) booking's reference, **When** the
   endpoint is called, **Then** the page shows PENDING.
4. **Given** an expired (unpaid) booking's reference, **When** the endpoint is
   called, **Then** the page shows EXPIRED.
5. **Given** an unknown/arbitrary reference, **When** the endpoint is called,
   **Then** it returns 404 and reveals nothing about whether any other booking
   exists.
6. **Given** any verification response, **When** it is inspected, **Then** it
   contains no traveler name, email, phone, payment info, guest identity,
   internal IDs, or partner notes.
7. **Given** a future redemption feature, **When** it is added later, **Then**
   the QR format is unchanged (the same URL continues to resolve) and only the
   status set gains a USED value.

---

### Edge Cases

- A booking is confirmed while the traveler's email address is undeliverable —
  retries exhaust, an admin alert is raised, the booking remains confirmed, and
  the traveler can still download the voucher from their dashboard (registered)
  or contact support (guest).
- A guest booking's confirmation email fails — the guest has no dashboard; the
  admin alert is the only remediation path in Phase 1 (no token-based guest
  download link).
- A partner is suspended while they have unread in-app notifications — the
  notifications remain accessible to the partner on next sign-in (suspension does
  not purge the notification history).
- Two jobs are dispatched for the same booking (double dispatch) — the
  idempotency lock plus the "already sent" timestamp guard MUST prevent a second
  email pair.
- A voucher generation succeeds but the voucher email send fails — the
  confirmation email has already gone out; the voucher failure is logged and the
  traveler can still download the voucher from their dashboard.
- A booking transitions from `confirmed` to `completed` — the voucher remains
  downloadable (proof of a past booking).
- A booking is cancelled after a voucher was already generated — the downloadable
  voucher MUST no longer be served; any previously emailed PDF is outside the
  platform's control but the dashboard path is closed.
- A locale is set to a value with no template (e.g. a regional variant) — the
  system falls back to EN, never crashes, and never silently sends an empty
  email.
- A partner with zero notifications opens the notifications list — an empty
  state is shown (no error).
- A notification job is retried after a partial success (email sent, in-app
  notification not written) — the job is retry-safe: the in-app notification is
  created on retry, the email is not re-sent.
- A voucher QR is scanned months after a booking is completed — the verification
  page still resolves and shows VALID (the URL is durable; the booking
  reference is the permanent public key).
- An attacker enumerates references against the verification endpoint — every
  unknown reference returns 404 identically; no timing or content side-channel
  reveals whether a reference exists, and no listing endpoint is exposed.
- A voucher is forged by editing the PDF — the QR still resolves to the real
  verification URL, so a forged reference that does not exist returns 404 and a
  cancelled booking's QR shows CANCELLED on scan, exposing the forgery.
- A booking moves PENDING → CONFIRMED between a voucher scan and a refresh — the
  verification page reflects the current status on each load (it is not cached
  long enough to show a stale status for a booking whose state just changed).
- A traveler opens `/v/{reference}` for their own cancelled booking — the page
  shows CANCELLED; no voucher download is offered from this surface (the
  dashboard path is the one closed by FR-008).
- A future redemption (`USED`) feature is added — the existing QR format and URL
  scheme remain unchanged; only the status set gains a new value, and previously
  issued vouchers keep resolving correctly.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST send a localized booking confirmation email to the
  traveler when a booking's payment is captured.
- **FR-002**: The system MUST generate a voucher PDF for each confirmed booking
  containing the booking reference, a QR code encoding the public verification
  URL `https://bookly.travel/v/{booking_reference}` (NOT the bare reference, NOT
  a JSON payload), tour title, tour date, participant count, total paid, booking
  status, and meeting point. The QR MUST resolve to the public verification
  surface (FR-021, FR-027).
- **FR-003**: The system MUST send a localized voucher email to the traveler with
  the voucher PDF attached, following the confirmation email.
- **FR-004**: The system MUST notify the partner of a new booking on their tour
  (in-app notification and email) when a booking is confirmed.
- **FR-005**: The system MUST notify the partner of a booking cancellation (in-app
  and email) when a booking is cancelled.
- **FR-006**: The system MUST notify a partner by email when their application is
  approved, rejected (with reason), or when they are suspended (with reason);
  notification delivery for these governance decisions is owned by this spec per
  the dependency declared in Spec `013` FR-007.
- **FR-007**: The system MUST allow the booking owner to download their voucher
  PDF from their booking detail page at any time for any post-payment,
  non-cancelled booking (`confirmed`, `completed`).
- **FR-008**: The system MUST refuse voucher download for any user who is not the
  booking owner, for unauthenticated visitors, and for cancelled bookings.
- **FR-009**: The system MUST deliver vouchers to guest travelers (no account) by
  email only; the dashboard voucher download path is auth-gated and unavailable
  to guests. A token-based guest download link is out of scope for Phase 1.
- **FR-010**: The system MUST dispatch all notification delivery (email and in-app)
  as queued, asynchronous jobs; delivery MUST NOT block the booking flow or run
  synchronously in the request cycle.
- **FR-011**: Every notification job MUST be retry-safe and idempotent: a retried
  job MUST NOT produce duplicate emails or duplicate in-app notifications; an
  "already sent" / "already created" guard MUST prevent duplicates on
  re-dispatch.
- **FR-012**: The system MUST bound notification retries (finite attempts with
  back-off); when retries are exhausted, the system MUST raise a failure event
  and alert admins through the existing channels — an ERROR-level log entry
  (always) and a best-effort Slack webhook alert (when
  `services.slack.admin_webhook_url` is configured) — containing the booking
  reference and the failure reason. Slack is best-effort only and MUST NOT fail
  the original listener/job if Slack itself fails. The system MUST NOT introduce
  an in-app admin notification surface (no `admin_notifications` table, no admin
  `Notification` model, no Filament Notification resource, no unread/read
  state, no admin inbox).
- **FR-013**: Notification delivery failures (including exhausted retries and
  voucher-generation failures) MUST NOT alter the booking's status; a paid
  booking remains confirmed regardless of email-side outcomes.
- **FR-014**: The system MUST localize traveler-facing emails (confirmation,
  voucher, cancellation) and partner-facing emails to the booking's or partner's
  locale (EN, ES, IT), with EN as the fallback when a localized template is
  missing.
- **FR-015**: The system MUST localize the voucher PDF's labels and content to
  the booking's locale, with EN fallback.
- **FR-016**: The partner in-app notification center MUST support: an unread
  count, a paginated list (with unread-only filter), mark-one-as-read, and
  mark-all-as-read; partners MUST only ever see their own notifications.
- **FR-017**: The partner in-app notifications MUST be reachable from the partner
  dashboard with a live unread indicator (not a static zero) so partners are
  surfaced new notifications without visiting a separate page.
- **FR-018**: The system MUST regenerate a stale voucher when a booking's date or
  participant count changes after confirmation, so a downloaded voucher always
  matches the current booking; unchanged bookings reuse the existing voucher.
- **FR-019**: The system MUST log notification delivery failures (transient and
  exhausted) at ERROR level with enough operational context — booking
  reference, mail class, exception message, and queue/job information where
  available — for operational follow-up, reusing the existing
  `NotifyAdminOnEmailDeliveryFailure` listener and Slack webhook integration.
  Logs and Slack alerts MUST NEVER include sensitive payment information or PII
  beyond what is needed to locate the booking. No new logging infrastructure is
  introduced.
- **FR-020**: The system MUST NOT introduce automated partner payout
  notifications; payouts are out of scope for Phase 1 (constitution Out-of-Scope
  §1) and any payout notification work requires a future constitution amendment.
- **FR-021**: The system MUST expose a public, unauthenticated, read-only
  verification API endpoint that resolves a booking reference and returns a
  `VerificationResult` JSON payload confirming the booking's authenticity. The
  endpoint MUST require no authentication, MUST NEVER modify booking state, and
  MUST NOT produce any side effect observable by the caller.
- **FR-022**: The verification response MUST expose only: booking reference,
  verification status, tour title, scheduled tour date, and participant count;
  it MAY additionally include the booking created date and the voucher-generated
  timestamp. It MUST NEVER expose traveler name, email, phone, payment
  information, guest identity, internal database IDs, or partner internal notes.
- **FR-023**: The verification status MUST reflect the booking lifecycle and
  span at minimum: `VALID` (confirmed or completed), `CANCELLED`, `PENDING`
  (awaiting payment), and `EXPIRED` (pending booking that expired unpaid). The
  design MUST naturally support a future `USED` (redeemed) state without changing
  the QR code format or the verification URL scheme.
- **FR-024**: The verification endpoint MUST use the opaque booking reference as
  the public lookup key; it MUST NOT accept or expose numeric database IDs.
  Unknown references MUST return 404, and the endpoint MUST NOT reveal whether
  any other booking exists (no enumeration, no listing, no listing side-channel).
- **FR-025**: The verification endpoint MUST be strictly read-only: it MUST
  NEVER increment counters, write audit entries keyed to the visitor, log
  visitor identity, or mutate any state. (Governance audit logging of admin
  actions is owned by Spec `013`; the public verification surface writes
  nothing.)
- **FR-026**: The verification endpoint MUST be backed by a dedicated controller
  that delegates to a single reusable `VerificationAction` and returns a
  `VerificationResult` DTO through a `VerificationTransformer`, with no business
  logic in the controller — consistent with the project's thin-controller /
  domain-action architecture. If verification logic is needed in more than one
  place, it MUST be extracted into that one Action, never duplicated.
- **FR-027**: A minimal public Next.js verification page MUST render at
  `/v/{booking_reference}` (per the constitution's API-First rule for public
  surfaces) and consume the verification API. It MUST show a large, prominent
  status indicator (e.g. VALID / CANCELLED) alongside only the allowed fields,
  require no authentication, and NOT be part of any dashboard; it has no
  navigation to private surfaces. The voucher QR (FR-002) encodes this page's
  public URL.
- **FR-028**: The verification surface MUST NOT break any existing flow —
  booking, notifications, payments, partner dashboard, traveler dashboard,
  search, or admin moderation. It is purely additive (new public read route +
  minimal page + QR payload change) and shares the existing Booking domain
  without duplicating booking-read logic.

### Key Entities *(include if feature involves data)*

- **Booking**: The reservation whose lifecycle triggers notifications; carries a
  `locale` (EN/ES/IT), a `reference`, a `confirmation_email_sent_at` idempotency
  timestamp, a `traveler` (registered) or guest identity, a `tour`, and a
  `status` that governs voucher download eligibility.
- **Voucher**: The generated PDF artifact for a booking; stored against the
  booking reference; regenerated when booking details change; contains booking
  reference, QR code, tour details, date, participants, total paid, and meeting
  point.
- **Notification (in-app, partner)**: An in-app notification record owned by a
  partner, with a type, title, body, structured data, and a read/unread state;
  scoped to one partner only.
- **Mailable / Email Template**: A localized email template (EN/ES/IT) used to
  render a transactional email (booking confirmed, voucher, cancellation, partner
  approved/rejected/suspended, new booking); selected by booking/partner locale
  with EN fallback.
- **DeliveryFailure / Admin Alert**: A record surfaced to admins when a
  notification job exhausts its retries, capturing the booking reference, channel,
  and failure reason for manual follow-up.
- **AuditLog**: The append-only governance log (owned by Spec `013`) that
  captures admin governance actions; this spec does NOT write notification
  delivery failures into the governance audit trail. Delivery-failure alerting
  is owned by this spec and uses the operational ERROR log + optional Slack
  webhook (reusing `NotifyAdminOnEmailDeliveryFailure`), not the governance
  audit store.
- **DeliveryFailure / Admin Alert**: An operational alert surfaced to admins
  when a notification job exhausts its retries — an ERROR-level log entry
  (always) and a best-effort Slack webhook message (when configured) — capturing
  the booking reference, mail class, exception, and queue/job context for manual
  follow-up. It is NOT a persisted in-app notification object; no admin
  notifications table, Filament resource, or unread/read state is introduced.
- **VerificationResult**: The read-only payload returned by the public
  verification endpoint, composed of a verification status (`VALID` / `CANCELLED`
  / `PENDING` / `EXPIRED`, future `USED`) plus the allowed public booking fields
  (reference, tour title, tour date, participant count, optionally created date
  and voucher-generated timestamp). It is derived from a `Booking` by a
  `VerificationAction` and serialized by a `VerificationTransformer`; it never
  carries PII or internal IDs.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of confirmed bookings produce exactly one localized
  confirmation email and one localized voucher email to the traveler, and one
  new-booking notification to the partner — no duplicates, no silent skips.
- **SC-002**: 100% of voucher downloads by non-owners, unauthenticated visitors,
  or for cancelled bookings are refused; zero unauthorized voucher downloads
  succeed.
- **SC-003**: A traveler who completes booking in under 2 minutes receives their
  confirmation email within 2 minutes and their voucher email within 5 minutes
  under normal conditions.
- **SC-004**: 100% of notification delivery failures (exhausted retries) raise an
  admin alert; zero delivery failures silently drop without an admin-visible
  record.
- **SC-005**: Zero booking status changes result from email delivery failures —
  a paid booking stays confirmed regardless of email-side outcomes (verified
  across transient failure, exhausted retries, and voucher-generation failure).
- **SC-006**: 100% of traveler-facing emails render in the booking's locale
  (EN/ES/IT) when the template exists, with EN fallback when it does not; zero
  locale-mismatched or empty-body emails.
- **SC-007**: Partners can view, filter (unread-only), and mark-read their in-app
  notifications, with the dashboard unread indicator reflecting the true unread
  count (never a static zero) within seconds of a new notification.
- **SC-008**: A booking whose date or participant count changes after
  confirmation yields a voucher whose content matches the current booking on the
  next download, in 100% of cases; unchanged bookings reuse the existing voucher
  (no unnecessary regeneration).
- **SC-009**: 100% of voucher QR codes encode the public verification URL
  `https://bookly.travel/v/{booking_reference}` and resolve to the verification
  page; zero encode a bare reference or a JSON payload.
- **SC-010**: 100% of verification responses for unknown references return 404;
  zero verification responses expose traveler name, email, phone, payment info,
  guest identity, internal IDs, or partner notes (verified by automated PII-
  leakage tests across all states).
- **SC-011**: The verification endpoint is read-only with zero side effects:
  across VALID / CANCELLED / PENDING / EXPIRED / unknown calls, no booking state,
  no counter, and no audit entry keyed to the visitor is ever written.

## Assumptions

- The notification and voucher infrastructure already substantially exists in the
  codebase and is **reused, not redefined**, by this spec: localized booking
  confirmed/voucher email views (EN/ES/IT), `BookingConfirmedMail`,
  `BookingVoucherMail`, `BookingCancelledMail`, partner mailables
  (`PartnerApprovedMail`, `PartnerRejectedMail`, `PartnerNewBookingMail`,
  `PartnerBookingCancelledMail`), `VoucherService` (PDF + QR generation),
  `VoucherController` (auth-gated download), the queued idempotent
  `SendBookingConfirmationEmail` job (lock + `confirmation_email_sent_at` guard,
  3 retries, `failed()` hook), the `BookingEmailDeliveryFailed` event and
  `NotifyAdminOnEmailDeliveryFailure` listener, and the partner in-app
  `Notification` model + `NotificationService` + `NotificationController`. This
  spec formalizes the requirements these implement and identifies the remaining
  gaps for the plan phase.
- **Guest voucher delivery is email-only in Phase 1.** A token-based guest
  download link is a documented future enhancement, not a Phase 1 gap. Guests
  receive their voucher PDF by email at confirmation time.
- **Voucher download is extended to `completed` bookings** (in addition to the
  currently-guarded `confirmed`) so past bookings remain provable; this is a
  scope item for the plan, not a redefinition of the existing `confirmed`-only
  guard.
- **Admin manual re-send of failed emails is deferred.** Phase 1 provides
  automatic retry + admin alerting only; a manual re-send action in the admin
  panel is out of scope here.
- **Travelers are email-only in Phase 1** — no traveler in-app notification
  center is built in this spec. The partner in-app notification center already
  exists and is completed here (live unread indicator).
- **Partner payout notifications are out of scope** per the constitution
  (Out-of-Scope §1 — automated partner payouts); they require a future
  amendment before being specified.
- **Audit logging** of governance actions is owned by Spec `013`
  (`governance_audit_logs`); this spec does NOT write notification delivery
  failures into the governance audit trail. Delivery-failure alerting uses the
  operational ERROR log + optional Slack webhook (reusing the existing
  `NotifyAdminOnEmailDeliveryFailure` listener), with no new in-app admin
  notification surface, no new table, and no new Filament resource.
- **Booking financial state, refunds, and ledger entries** are owned by Spec
  `008` (Payments & Finance); this spec never touches financial state — email
  delivery failures never affect booking status or the ledger.
- **Review-submitted notifications** (partner notified of a new review) are owned
  by Spec `009`; this spec does not redefine them, though the partner in-app
  notification center built here is the surface that delivers them.
- **Auth and role/permission infrastructure** (Laravel Sanctum, partner role)
  is reused; no new auth mechanism is introduced.
- All notification work remains subject to the constitution's strict
  authorization, mandatory input validation, idempotent-financial-flows, and
  queueing/async-work requirements.
- The existing voucher PDF view generates the QR from the booking reference;
  this spec changes the QR payload to the public verification URL
  (`https://bookly.travel/v/{booking_reference}`). The plan phase migrates the
  QR generation to encode the URL and adds the public verification API endpoint
  (`VerificationAction` + `VerificationTransformer` + controller), the minimal
  Next.js `/v/{booking_reference}` page, and the `VALID` / `CANCELLED` / `PENDING`
  / `EXPIRED` status mapping — reusing the existing Booking domain and opaque
  booking references, with no duplication of booking-read logic.
- The public verification surface (API endpoint + page) is a NEW surface owned
  by this spec. It is read-only, unauthenticated, and intentionally NOT a
  dashboard. Per the constitution's API-First rule, the page is Next.js and
  consumes the Laravel verification API (the Filament admin exception does NOT
  apply here). Constitution alignment — including the read-only / no-PII /
  no-enumeration constraints and the API-First split — will be confirmed in the
  plan's Constitution Check gate.
- Backward compatibility is a hard requirement: the verification surface and QR
  payload change MUST NOT break the existing booking, notifications, payments,
  partner dashboard, traveler dashboard, search, or admin moderation flows. The
  change is purely additive (new public read route + minimal page + QR payload
  update) and shares the existing Booking domain.