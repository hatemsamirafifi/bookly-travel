# Bookly — Phase 2 Implementation Plan

> **Version**: 1.0.0  
> **Date**: 2026-08-22  
> **Status**: Awaiting Approval  
> **Prerequisite**: Fix all 9 critical findings in `016-REVIEW.md` before Phase 2 begins.

---

## Background & Context

Phase 1 delivered the full tours marketplace MVP: traveler auth, discovery, booking, payments, reviews, notifications, vouchers, traveler account, partner onboarding, tour management, admin moderation, and the blog. All specs `001–016` are implemented and merged.

Phase 2 moves **beyond the MVP** to add monetization infrastructure, operational maturity, conversion optimizations, and platform growth features — all explicitly deferred from Phase 1 in the PRD.

---

## Prerequisite: 016 Blog Remediation (Before Phase 2 Starts)

> [!CAUTION]
> The `016-REVIEW.md` contains **9 critical bugs** that make the blog non-functional end-to-end. These MUST be fixed before any Phase 2 work begins.

| ID | Files | Fix |
|----|-------|-----|
| CR-001 | `BlogPost`, `UpdateBlogPostAction`, pivot migration | Add `blog_category_post` pivot + `categories()` relation; fix `relatedTours()` callers |
| CR-002 | Blog migrations, `$fillable`, Filament pages | Add `meta_title` JSONB column; replace `getTranslation()` with `contentFor()` |
| CR-003 | `GetBlogPostPreviewAction:55` | Add `ctype_digit($expiresAt)` guard before expiry comparison |
| CR-004 | `PublishScheduledBlogPostJob` | Set `published_at` in job; don't set it when `scheduled_at` is future |
| CR-005 | `BlogPostResource.php:9` | Change `App\Domains\Tour\Models\Tour` → `App\Models\Tour` |
| CR-006 | `GetBlogPostPreviewAction:70` | Inject `BlogPostDetailTransformer`; fix eager-loads to `relatedTours`, `category` |
| CR-007 | `blog.ts`, `types.ts`, `BlogDetail.tsx`, `BlogFeaturedHero.tsx` | Align field names: `display_name`, `cover_image_url`, `category`, `reading_time`; define `BlogArticleDetailResponse` |
| CR-008 | `UpdateBlogPostAction` | Add `blog.slug_change` audit event; document slug-uniqueness invariant |
| CR-009 | `InvalidateBlogCacheJob:32-37` | Remove `Cache::flush()` fallback; use explicit key `forget()` |

**Resolution**: `fix/016-blog-remediation` branch using the Spec-Kit `speckit-implement` workflow.

---

## Phase 2 Feature Set

Phase 2 is organized into **8 specs** (017–024) across **4 execution waves**.

### Feature Map

| Spec | Feature | Wave | PRD Source | Business Value |
|------|---------|------|-----------|----------------|
| `017` | Partner Payouts & Commission Ledger | 1 | PRD §19 | Revenue distribution; partner trust |
| `018` | Automated Refunds & Cancellation Policies | 1 | PRD §19 | Reduce manual ops; buyer confidence |
| `019` | Social Login (OAuth — Google & Facebook) | 1 | PRD §19 | Reduce registration friction; conversion |
| `020` | Multi-Staff Partner Accounts | 2 | PRD §19 | Unlock SME partners with teams |
| `021` | Tiered Pricing (Adult / Child / Infant) | 2 | PRD §19 | Unlock family segment; revenue uplift |
| `022` | Partner Replies to Reviews | 3 | PRD §19 | Partner engagement; trust signal |
| `023` | SMS / Push Notifications | 3 | PRD §19 | Engagement lift; reduce no-shows |
| `024` | Advanced Analytics Dashboard | 4 | PRD §21 | Data-driven decisions; partner retention |

---

## Wave 1 — Financial & Growth Foundation (Specs 017, 018, 019)

These three specs are **independent of each other** and can be worked in parallel. They are the highest-ROI items: payouts unlock real revenue flow, automated refunds reduce ops burden, social login improves conversion.

---

### Spec 017 — Partner Payouts & Commission Ledger

**Objective**: Build an automated payout system that calculates Bookly's platform commission, tracks partner earnings, and disburses funds via Stripe Connect.

**Key Constraints**:
- Phase 1 `financial_ledger` is immutable — Phase 2 builds alongside it, never edits it.
- No payout before booking is `completed`.
- Commission rate is platform-configurable per partner (stored in `partners` table, defaulting to platform default from `spatie/laravel-settings`).

**New Backend Domain**: `Finance/Payouts`

| Component | Detail |
|-----------|--------|
| **New models** | `PartnerEarning` (immutable, one per confirmed booking), `PartnerPayout` (batched disbursement), `PayoutLineItem` |
| **New tables** | `partner_earnings` (booking_id, partner_id, gross_amount, commission_rate, commission_amount, net_amount, currency, status, earned_at); `partner_payouts` (partner_id, total_amount, currency, stripe_transfer_id, status, initiated_at, completed_at); `payout_line_items` (payout_id, earning_id) |
| **Stripe** | Stripe Connect Express; `POST /v1/transfers`; `account.updated` webhook for KYC |
| **Actions** | `CalculatePartnerEarningAction`, `InitiatePayoutAction`, `ReconcilePayoutAction` |
| **Jobs** | `ProcessPartnerPayoutJob` (queued, idempotent), `ReconcilePayoutStatusJob` (daily cron) |
| **Filament** | `PayoutResource` (list, view, initiate, hold, release); `EarningResource` (read-only); `PartnerResource` — Stripe account status widget |
| **Partner API** | `GET /api/partner/earnings`, `GET /api/partner/payouts` |
| **Admin API** | `GET /api/admin/payouts`, `POST /api/admin/payouts/{id}/initiate`, `/hold`, `/release` |

**New Webhook**: `POST /api/webhooks/stripe/connect` (separate signing secret from Phase 1)

**Frontend**:
- `/partner/earnings` — period selector, commission breakdown, pending/paid totals, Recharts timeline
- `/partner/payouts` — payout history table, Stripe Connect onboarding CTA if not connected
- Filament: `PayoutResource`, `EarningResource` pages

**Verification Gates**:
- `partner_earnings` row created on `payment_intent.succeeded` with correct `commission_amount`
- No payout initiated for non-`completed` bookings
- Stripe Transfer dispatched with correct amount and Connect destination
- Admin can hold/release payouts
- `php artisan test --filter PayoutTest` + `npm run test:e2e -- --grep "payout"`

---

### Spec 018 — Automated Refunds & Cancellation Policies

**Objective**: Replace manual Stripe-dashboard refunds with in-app automated refunds; add configurable per-tour cancellation policies; support partial refunds.

**Key Constraints**:
- Phase 1: manual refunds only via `charge.refunded` webhook.
- Phase 2: app initiates refunds via `stripe->refunds->create()`; `financial_ledger` remains append-only.
- Partial refunds are now in scope (excluded in Phase 1 PRD).

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **New model** | `CancellationPolicy` (belongs to `Tour`): `free_cancel_before_hours`, `partial_refund_percent`, `partial_refund_before_hours`, `no_refund_after_hours` |
| **New table** | `cancellation_policies` (tour_id FK, policy fields) |
| **Actions** | `CalculateRefundAction` (policy engine), `IssueStripeRefundAction` (idempotent via idempotency key) |
| **Jobs** | `ProcessRefundJob` (queued, retry-safe) |
| **Webhook extension** | `charge.refunded` — add `refund_type: automated|manual` to `metadata` |
| **Partner API** | `GET/PUT /api/partner/tours/{id}/cancellation-policy` |
| **Admin Filament** | `BookingResource` detail: refund amount, policy applied, Stripe refund ID |

**Frontend**:
- Tour detail: cancellation policy badge ("Free cancellation until 24h before" / "Non-refundable")
- Checkout step 3: policy disclosure before payment confirmation
- Booking detail: "Cancel & Refund" button with refund amount preview modal
- Partner tour editor: `CancellationPolicyForm` component

**Verification Gates**:
- Free-cancel window: full automated refund via Stripe
- Partial-refund window: `Math.floor(amount * percent / 100)` charged
- No-refund window: booking cancelled, no Stripe call made
- `IssueStripeRefundAction` is idempotent — second call with same key returns existing refund
- `php artisan test --filter RefundPolicyTest`

---

### Spec 019 — Social Login (OAuth)

**Objective**: Add Google and Facebook OAuth login to reduce registration friction.

**Key Constraints**:
- Must not break existing Sanctum token auth.
- Social login users get a full `User` record; link by email if account already exists.
- Social accounts auto-satisfy email verification.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **Packages** | `laravel/socialite`, `socialiteproviders/facebook` |
| **New table** | `social_accounts` (user_id, provider `google\|facebook`, provider_user_id, access_token, refresh_token, token_expires_at) |
| **Controller** | `SocialAuthController`: `redirect()`, `callback()` per provider |
| **Service** | `SocialAuthService`: find-or-create user by email; link `social_accounts`; issue Sanctum token |
| **Routes** | `GET /api/public/auth/{provider}/redirect`, `GET /api/public/auth/{provider}/callback` |

**Frontend**:
- Login and Register pages: "Continue with Google" / "Continue with Facebook" buttons
- `lib/api/auth.ts`: `initiateSocialLogin(provider)`, `handleSocialCallback(provider, code)` helpers
- Token storage identical to email login flow

**Verification Gates**:
- New user via Google: `User` + `social_accounts` row created, Sanctum token returned
- Existing email user signs in via Google: accounts linked, no duplicate `User`
- Facebook OAuth end-to-end in staging
- Guest checkout still works (no OAuth required)
- `php artisan test --filter SocialAuthTest`

---

## Wave 2 — Partner & Revenue Expansion (Specs 020, 021)

Depends on Wave 1 being merged. Both specs can proceed in parallel.

---

### Spec 020 — Multi-Staff Partner Accounts

**Objective**: Allow one partner business to have multiple staff members with role-based access (owner, manager, staff).

**Key Constraints**:
- Phase 1: `partner_id` is 1:1 with `user_id` — this assumption is pervasive.
- Phase 2: introduce `partner_members` pivot without breaking existing single-user partners.
- `partner_id` FK on `tours`, `bookings`, `partner_earnings` does NOT change.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **New table** | `partner_members` (partner_id, user_id, role `owner\|manager\|staff`, invited_by, accepted_at, created_at) |
| **Roles** | `owner`: full access; `manager`: tours/availability/pricing — no billing; `staff`: bookings read-only |
| **Actions** | `InvitePartnerMemberAction` (signed URL email), `AcceptPartnerInvitationAction`, `RemovePartnerMemberAction` |
| **Middleware** | Extend `PartnerMiddleware` to check `partner_members` membership |
| **Migration** | Seed `partner_members` owner row for all existing partners |
| **Partner API** | `GET /api/partner/team`, `POST /api/partner/team/invite`, `DELETE /api/partner/team/{userId}` |
| **Filament** | `PartnerResource`: members tab; admin can remove/change roles |

**Frontend**:
- `/partner/team` — member list, invite form, role badges, remove button
- Role-gated UI: managers cannot see Earnings/Payouts; staff see bookings read-only
- New email: `PartnerInvitationMail` (EN/ES/IT, 72h expiry invite link)

**Verification Gates**:
- Existing partners function after migration (owner row seeded)
- Manager: `GET /api/partner/earnings` → 403
- Staff: `POST /api/partner/tours` → 403
- Invitation link expires after 72h
- `php artisan test --filter PartnerTeamAccessTest`

---

### Spec 021 — Tiered Pricing (Adult / Child / Infant)

**Objective**: Replace single per-person pricing with age-tiered pricing: adult, child (2–11), infant (<2), plus group discounts.

**Key Constraints**:
- Phase 1: `tour_pricings` has one row per tour.
- Phase 2: replace with `tour_pricing_tiers`. Existing tours migrate to an `adult` tier automatically.
- `bookings.total_amount` remains an integer (cents) — only the source calculation changes.

> [!WARNING]
> This is the highest-risk migration in Phase 2. Use a zero-downtime strategy: (1) create new table, (2) backfill from old table, (3) dual-write period, (4) switch reads, (5) drop old table.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **New table** | `tour_pricing_tiers` (tour_id, tier `adult\|child\|infant\|group`, label, amount_cents, currency, min_age, max_age, min_participants) |
| **Migration** | Backfill `tour_pricings` → `adult` tier; then drop `tour_pricings` |
| **Booking additions** | `adult_count`, `child_count`, `infant_count` columns; `pricing_snapshot JSONB` |
| **Action** | `CalculateBookingPriceAction`: loops tiers × counts; returns line-item breakdown |
| **API changes** | Tour detail pricing section → tiers array; `POST /api/public/bookings` → accepts per-tier counts |
| **Partner API** | `PUT /api/partner/tours/{id}/pricing` → accepts array of tiers |

**Frontend**:
- Tour detail: `PricingTiers` component
- Checkout `ParticipantSelector`: adult/child/infant spinners with min/max guards
- `PriceBreakdown`: line-items per tier × count + total
- Partner `PricingTierForm`: CRUD for tier rows with age-range inputs
- `BookingCard`/`BookingDetail`: tier breakdown from `pricing_snapshot`

**Verification Gates**:
- Existing tours migrated: `adult` tier with same amount
- Price: `adult_count × adult_price + child_count × child_price = total_amount`
- `pricing_snapshot` frozen at booking creation
- Infant-only booking rejected (min 1 adult required)
- `php artisan test --filter TieredPricingTest`

---

## Wave 3 — Engagement & Trust (Specs 022, 023)

Both specs are independent of each other and of Wave 2. They can run in parallel with Wave 2.

---

### Spec 022 — Partner Replies to Reviews

**Objective**: Allow approved partners to post one official reply per review, displayed publicly below the review.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **New table** | `review_replies` (review_id, partner_id, body, created_at, updated_at) — one-to-one with `reviews` |
| **Actions** | `CreateReviewReplyAction` (partner-only, tour ownership check), `DeleteReviewReplyAction` (partner or admin) |
| **API** | `POST /api/partner/reviews/{review}/reply`, `DELETE /api/partner/reviews/{review}/reply`; reply included in `GET /api/public/tours/{slug}/reviews` |
| **Filament** | `ReviewResource`: show reply inline; admin can delete |
| **New email** | `PartnerReviewReceivedMail` — notify partner when a review is posted on their tour (EN/ES/IT) |

**Frontend**:
- Tour detail `ReviewCard`: reply bubble ("Response from [Partner Name]")
- Partner `ReviewsPage`: "Reply" button → collapsible form; "Edit" / "Delete" if reply exists

**Verification Gates**:
- Partner can only reply to reviews on their own tours (403 for others)
- One reply per review — second `POST` returns 409
- Admin can delete any reply
- Reply appears in public tour detail API
- `php artisan test --filter ReviewReplyTest`

---

### Spec 023 — SMS / Push Notifications

**Objective**: Add SMS and web push notifications for booking events and reminders.

**Key Constraints**:
- Phase 1: email only. Phase 2 adds SMS (Twilio) and web push (VAPID).
- All new channels are **opt-in**. Email remains primary and is always sent.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **Packages** | `twilio/sdk`, `minishlink/web-push` |
| **New table** | `notification_preferences` (user_id, channel `sms\|push`, event `booking_confirmed\|booking_reminder\|booking_cancelled`, enabled bool) |
| **New table** | `push_subscriptions` (user_id, endpoint, p256dh, auth, created_at) |
| **SMS triggers** | Booking confirmed (immediate); 24h-before-tour reminder (scheduled job) |
| **Push triggers** | Booking confirmed; voucher ready; wishlist tour availability change |
| **Jobs** | `SendSmsNotificationJob`, `SendPushNotificationJob` — queued independently of email |
| **API** | `PUT /api/public/notification-preferences`, `POST/DELETE /api/public/push-subscriptions` |

**Frontend**:
- Profile/Settings: `NotificationPreferences` component (SMS opt-in + phone validation; push opt-in via `Notification.requestPermission()`)
- `public/sw.js` service worker for push click-through to booking detail
- i18n: SMS templates EN/ES/IT (< 160 chars)

**Verification Gates**:
- SMS sent on confirmation when opted in; skipped when opted out
- 24h reminder job skips past-date bookings
- Push VAPID handshake succeeds
- `php artisan test --filter SmsNotificationTest`

---

## Wave 4 — Analytics & Observability (Spec 024)

### Spec 024 — Advanced Analytics Dashboard

**Objective**: Replace basic `AnalyticsSummary`/`BookingsChart` with a full analytics dashboard — partner-level and platform-level reporting, funnel analysis, and export.

**Key Constraints**:
- All analytics are **read-only**.
- Data sourced from existing tables — no separate OLAP store in Phase 2.
- Redis-cached aggregations with 15-minute TTL.

**New Backend Components**:

| Component | Detail |
|-----------|--------|
| **Service** | `AnalyticsService` — time-bucketed aggregations (day/week/month) |
| **API** | `GET /api/partner/analytics/revenue`, `/bookings`, `/tours`, `/export?format=csv&period=30d`; `GET /api/admin/analytics/platform` |
| **Caching** | `AnalyticsCacheJob` pre-warms hourly; keys: `analytics:partner:{id}:{metric}:{period}` |
| **New table** | `conversion_events` (session_id, event `page_view\|tour_view\|checkout_start\|booking_complete`, tour_id nullable, created_at) — 90-day rolling retention |

**Frontend**:
- `/partner/analytics` full page:
  - KPI cards: Revenue, Bookings, Avg Booking Value, Review Score
  - `RevenueChart` (Recharts area, period selector: 7d/30d/90d/1y)
  - `TopToursTable` (top 5 tours by revenue)
  - `BookingFunnelChart` (detail views → bookings → confirmed)
  - `ExportButton` → CSV download
- Admin Filament: `PlatformAnalytics` page (GMV, partner growth, tour approval rates)
- `analytics.ts` client-side tracker: `POST /api/public/events`

**Verification Gates**:
- Revenue aggregation matches `financial_ledger` sum for partner's tours in period
- CSV export: valid UTF-8, correct headers
- Analytics API cached: second identical request served from Redis within TTL
- `php artisan test --filter AnalyticsServiceTest`

---

## Dependency Graph

```
          ┌──────────────────────────────────────────┐
          │     PREREQUISITE: fix/016-remediation      │
          └──────────────┬───────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        │                │                │
  ┌─────▼──────┐  ┌──────▼─────┐  ┌──────▼──────┐
  │  Spec 017   │  │  Spec 018   │  │  Spec 019   │
  │  Payouts    │  │  Automated  │  │  Social     │
  │  & Ledger   │  │  Refunds    │  │  Login      │
  └─────┬───────┘  └────────────┘  └─────────────┘
        │
   ┌────┴─────────────────┐
   │                      │
 ┌─▼──────────┐   ┌───────▼──────┐
 │  Spec 020   │   │  Spec 021    │
 │ Multi-Staff │   │ Tiered       │
 │ Partners    │   │ Pricing      │
 └─┬──────────┘   └──────────────┘
   │
 ┌─┴──────────┬────────────┬─────────────┐
 │            │            │             │
┌▼──────┐  ┌──▼──────┐  ┌──▼─────────┐  │
│Spec 022│  │Spec 023 │  │ Spec 024   │  │
│Partner │  │SMS/Push │  │ Analytics  │  │
│Replies │  │Notifs   │  │ Dashboard  │  │
└────────┘  └─────────┘  └────────────┘  │
```

---

## New Spec Directories

| Directory | Spec |
|-----------|------|
| `specs/017-partner-payouts/` | Partner Payouts & Commission Ledger |
| `specs/018-automated-refunds/` | Automated Refunds & Cancellation Policies |
| `specs/019-social-login/` | Social Login (OAuth) |
| `specs/020-multi-staff-partners/` | Multi-Staff Partner Accounts |
| `specs/021-tiered-pricing/` | Tiered Pricing |
| `specs/022-partner-review-replies/` | Partner Replies to Reviews |
| `specs/023-sms-push-notifications/` | SMS / Push Notifications |
| `specs/024-advanced-analytics/` | Advanced Analytics Dashboard |

Each spec follows the standard pipeline:
```
/speckit.specify → /speckit.clarify → /speckit.plan → /speckit.tasks → /speckit.implement
```

---

## New Tech Additions

| Technology | Purpose | Spec |
|-----------|---------|------|
| Stripe Connect (Express) | Partner payout disbursement | 017 |
| `laravel/socialite` | OAuth provider abstraction | 019 |
| `socialiteproviders/facebook` | Facebook OAuth driver | 019 |
| `twilio/sdk` | SMS delivery | 023 |
| `minishlink/web-push` | VAPID web push notifications | 023 |
| Browser Push API + Service Worker | Client-side push subscription | 023 |

---

## Impact on Existing Data Models

| Existing Table | Change | Spec | Risk |
|----------------|--------|------|------|
| `partners` | Add `stripe_account_id`, `commission_rate_percent`, `stripe_onboarding_completed_at` | 017 | Low — additive |
| `bookings` | Add `adult_count`, `child_count`, `infant_count`, `pricing_snapshot JSONB` | 021 | Medium — backfill |
| `tour_pricings` | **Replaced** by `tour_pricing_tiers` | 021 | High — migration |
| `users` | No change — new `social_accounts` table added | 019 | Low |
| `reviews` | New `review_replies` table | 022 | Low — additive |
| `financial_ledger` | Add `refund_type` to existing `metadata JSONB` | 018 | Low — backward compat |

> [!WARNING]
> Spec 021's replacement of `tour_pricings` with `tour_pricing_tiers` is the highest-risk migration. Plan explicitly for zero-downtime dual-write strategy in the `021` spec.

---

## Cross-Cutting Concerns

### Security
- Stripe Connect webhooks require a **separate signing secret** from Phase 1
- OAuth `state` parameter must be validated (CSRF protection on callback)
- SMS content must not expose booking references in plaintext (use masked references)
- Push subscription endpoints must verify user ownership before dispatching

### i18n
- All new email templates (partner invite, review notification): EN/ES/IT
- SMS templates: EN/ES/IT, ≤ 160 chars per segment
- New UI copy for all Wave 1–4 features: add to `messages/en.json`, `es.json`, `it.json`

### Testing
- Each Wave 1 spec targets ≥ 80% Feature test coverage on new backend files (addressing `docs/test-coverage-gap-analysis.md`)
- Payout flows: use `stripe-mock` Docker image (already in dev compose)
- OAuth tests: `laravel/socialite` fake driver pattern
- Analytics API: seed fixture data and assert aggregation correctness

### Performance
- `AnalyticsService` aggregations: Redis-cached (15-min TTL); never run raw queries on page load
- `ProcessPartnerPayoutJob`: must be idempotent and complete in < 5s for 100-booking batches
- `partner_members(partner_id, user_id)` unique index required — added to every partner auth check

---

## Verification Plan

### Per-Wave Gates

| Wave | Gate | Command |
|------|------|---------|
| Prerequisite | CR-001 through CR-009 resolved | `npm run build` (no TS errors); `php artisan test` (blog tests pass) |
| Wave 1 | Payout created in Stripe for completed booking | `php artisan test --filter PayoutTest` |
| Wave 1 | OAuth login creates user and issues Sanctum token | `php artisan test --filter SocialAuthTest` |
| Wave 1 | Automated refund matches policy calculation | `php artisan test --filter RefundPolicyTest` |
| Wave 2 | Manager blocked from earnings endpoint | `php artisan test --filter PartnerTeamAccessTest` |
| Wave 2 | Tiered price = sum(tier × count) | `php artisan test --filter TieredPricingTest` |
| Wave 3 | SMS sent on opt-in; skipped on opt-out | `php artisan test --filter SmsNotificationTest` |
| Wave 3 | One reply per review enforced (409 on second) | `php artisan test --filter ReviewReplyTest` |
| Wave 4 | Revenue aggregation matches ledger sum | `php artisan test --filter AnalyticsServiceTest` |

### Global Acceptance Criteria

- `npm run build` — no TypeScript errors
- `npm run lint` — no ESLint errors
- `npm run test` — all Jest unit tests pass
- `npm run test:e2e` — all Playwright E2E tests pass
- `php artisan test` — all Pest Feature tests pass
- Lighthouse Performance ≥ 90 on all public pages (unchanged from Phase 1)
- No `Cache::flush()` calls introduced (CR-009 lesson)

---

## Open Questions

> [!IMPORTANT]
> These must be resolved before spec generation begins for the relevant wave:

1. **Stripe Connect type (Spec 017)**: Express (managed onboarding, faster) vs Custom (full UI control, more compliance)? **Recommendation**: Express for Phase 2.

2. **Commission structure (Spec 017)**: Fixed platform rate (e.g., 15%) for all partners, or per-partner negotiated rates? Per-partner rates require admin UI to set them.

3. **Payout frequency (Spec 017)**: Automatic weekly/monthly batches, or admin-triggered manual payouts? Manual is lower risk for Phase 2 launch.

4. **Tiered pricing age bands (Spec 021)**: Should age bands (child 2–11, infant < 2) be globally fixed or configurable per tour? Per-tour adds UX complexity.

5. **SMS provider (Spec 023)**: Twilio confirmed? Or MessageBird / AWS SNS? Choice determines which SDK to add.

6. **Analytics data retention (Spec 024)**: Rolling 90 days on `conversion_events` recommended to prevent unbounded table growth — confirm?

7. **Multi-currency (Spec 021)**: Should each pricing tier support its own currency, or is currency locked at tour level (as in Phase 1)?
