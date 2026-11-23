# EGM-005 Technical Design & Architecture Specification

**Status:** Pre-Code Architecture & Technical Design
**Date:** 2026-11-17
**Governance:** Bookly Constitution v3.0.0, AEO v2.0, Elephant-Goldfish Model (EGM v0.1.1)
**Parent Plans:** `docs/superpowers/plans/2026-11-10-bookly-supplier-portal.md`, `docs/designs/supplier-portal-egm003-governance-v3.md`
**Decisions Covered:** D1 (Draft Recovery & Concurrency), D2 (First-Save Idempotency), D3 (Explicit Departures), D4 (Independent Capacity Only), D5 (Participant-Count Tiered Pricing), D6 (Content Governance Preservation)

---

## 1. Executive Summary & Authoritative Decisions

EGM-005 completes the core operational and commercial engine for the Bookly Travel Supplier Portal (Phase A: Slices A2–A5). It supersedes earlier exploratory proposals for shared capacity pools and fixed group pricing with explicit owner-ratified product decisions.

### Authoritative Decisions Register (D1–D6)

| Decision | Area | Ratified Policy | Superseded Proposal |
|---|---|---|---|
| **D1** | Draft Recovery & Concurrency | Server-authoritative draft persistence in PostgreSQL (`tour_drafts`), cross-session resume, language-first unselected state preserved, optimistic concurrency token (`concurrency_token` / `revision`) for multi-tab collision detection (HTTP 409 conflict with server state return). | Client-only localStorage drafts, silent multi-tab overwrite. |
| **D2** | First-Save Idempotency | Client-generated stable UUID (`creation_id` / `Idempotency-Key`) scoped to the authenticated partner, enforced via unique constraint. Identical payload replay returns existing tour (HTTP 200/201); conflicting payload re-using key returns HTTP 409. | Title-based deduplication, un-idempotent create POSTs. |
| **D3** | Explicit Departures | Concrete bookable `tour_departures` records with independent UTC `starts_at`, IANA timezone, local cutoff, and status. Bounded recurrence expansion up to 180 days with DST transition safety. | Day-level exclusive availability, implicit departure times. |
| **D4** | Independent Capacity Only | **OWNER-APPROVED:** No shared capacity pools. Every concrete departure of each tour option has its own independent capacity. A booking for Option A never consumes inventory of Option B or other dates. Pending bookings reserve capacity until `pending_expires_at`. | Shared capacity pools (`tour_capacity_pools`), cross-option vehicle pools. |
| **D5** | Participant-Count Tiered Pricing | **OWNER-APPROVED:** Participant-count-based tiered per-person pricing. Unit price per person depends on the total participant count of the booking. All participants pay the applicable tier unit price. Configurable per option in integer minor units (cents). | Fixed group pricing (`per_group`), private group flat fee, cumulative marginal pricing. |
| **D6** | Content Governance Preservation | Fully preserve accepted EGM-003 immutable content revision model: single active candidate, immutable revision IDs, field-specific change requests, 404 for ineligible owners, separation of commercial/operational data from editorial revisions. | Rebuilding or bypassing EGM-003 governance. |

---

## 2. Baseline Audit & Repository Inventory

- **Git Branch:** `codex/supplier-portal-hardening`
- **Git HEAD:** `324cb71cd6b46e36be27af799c7123d820a55f63`
- **Working Tree State:** 131 uncommitted paths preserved from EGM-003. No reset, stash, clean, commit, push, merge, or deploy will be executed without owner authorization.
- **Target-Environment Database Counts (Read-Only):**
  - `tours`: 8
  - `bookings`: 10
  - *Data Preservation Mandate:* The target environment is active and non-empty. All database migrations must be additive, nullable or default-backed, non-destructive, and reversible.
- **Verified Test Environments:**
  - Backend runner: `bookly-egm003-tests` container (Pest 15/15 passed on TourOptions, 1,479 total domain test suite).
  - Frontend runner: `frontend/` (Jest 86/86 test suites passed, 985/985 tests green).

---

## 3. Database Schema & Migration Architecture

### 3.1 Migration Strategy
All migrations are additive. Existing `tours`, `tour_options`, `tour_drafts`, `pricing_tiers`, and `bookings` tables are preserved and extended cleanly.

### 3.2 Additions and Modifications

#### 1. `tour_drafts` Table Enhancements
Existing table has: `id`, `tour_id` (nullable), `partner_id`, `payload` (json), `status`, `rejection_reason`, `auto_saved_at`, `created_at`, `updated_at`.
**Additions:**
- `creation_id` (`uuid`, nullable, unique per partner) - client-generated creation identifier for new drafts (D2).
- `concurrency_token` (`string`, 64 chars, nullable) - SHA-256 hash or monotonic counter for optimistic locking (D1).
- `current_step` (`string`, nullable, default 'language') - wizard step tracking.
- `source_locale` (`string`, 5 chars, nullable) - explicitly selected source language (`en`, `es`, `it`).

#### 2. `tour_departures` Table (New)
Concrete bookable occurrences (D3, D4):
```sql
CREATE TABLE tour_departures (
    id BIGSERIAL PRIMARY KEY,
    tour_id BIGINT NOT NULL REFERENCES tours(id) ON DELETE CASCADE,
    tour_option_id BIGINT NOT NULL REFERENCES tour_options(id) ON DELETE CASCADE,
    starts_at TIMESTAMPTZ NOT NULL, -- UTC authoritative
    timezone VARCHAR(64) NOT NULL DEFAULT 'UTC', -- IANA timezone (e.g. Europe/Rome)
    local_date DATE NOT NULL, -- Cached local calendar date YYYY-MM-DD
    local_time TIME NOT NULL, -- Cached local start time HH:MM:SS
    capacity INTEGER NOT NULL CHECK (capacity >= 1),
    booking_cutoff_hours INTEGER NOT NULL DEFAULT 2, -- Hours prior to starts_at
    status VARCHAR(32) NOT NULL DEFAULT 'active', -- 'active', 'cancelled', 'completed'
    recurrence_rule_id BIGINT NULL REFERENCES availability_rules(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_option_departure UNIQUE (tour_option_id, starts_at)
);

CREATE INDEX idx_departures_lookup ON tour_departures(tour_id, tour_option_id, local_date, status);
CREATE INDEX idx_departures_starts_at ON tour_departures(starts_at);
```

#### 3. `pricing_tiers` Table Enhancement
Existing table has: `id`, `tour_id`, `name`, `price` (decimal 10,2), `min_participants`, `max_participants`.
**Additions & Refinements (D5):**
- `tour_option_id` (`BIGINT`, nullable, references `tour_options(id)` on delete cascade).
- `price_amount` (`INTEGER`, nullable) - price per person in integer minor units (cents). If null, computed from `price * 100`.
- `currency` (`VARCHAR(3)`, not null, default 'EUR').
- `sort_order` (`INTEGER`, not null, default 0).
- Index: `(tour_option_id, min_participants, max_participants)`.

#### 4. `bookings` Table Extensions
Existing table has: `id`, `reference`, `traveler_id`, `tour_id`, `tour_option_id`, `tour_date`, `start_time`, `participant_count`, `price_per_person`, `total_price`, etc.
**Additions (D3, D4, D5):**
- `tour_departure_id` (`BIGINT`, nullable, references `tour_departures(id)` on delete set null).
- `pricing_tier_id` (`BIGINT`, nullable, references `pricing_tiers(id)` on delete set null).
- `pricing_snapshot` (`JSONB`, nullable) - captures:
  ```json
  {
    "pricing_mode": "tiered_per_person",
    "tier_id": 12,
    "tier_name": "Standard Group",
    "min_participants": 3,
    "max_participants": 4,
    "unit_price_cents": 800,
    "total_cents": 2400,
    "currency": "EUR",
    "participant_count": 3
  }
  ```

---

## 4. API & Contract Specifications

### 4.1 Server-Authoritative Draft APIs (D1, D2)

#### 1. Save / Update Draft
- **Endpoint:** `POST /api/partner/tours/drafts` or `PUT /api/partner/tours/drafts/{id}`
- **Headers:** `Authorization: Bearer <token>`, `If-Match: <concurrency_token>` (optional on create, mandatory on update)
- **Request Body:**
  ```json
  {
    "creation_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d", // UUID for first-save idempotency
    "tour_id": 42, // null if tour not yet materialized
    "source_locale": "es", // null if unselected
    "current_step": "options",
    "concurrency_token": "a1b2c3d4...",
    "payload": {
      "title": "Tour gastronómico en Florencia",
      "category_id": 3,
      "options": [ ... ]
    }
  }
  ```
- **Responses:**
  - `200 OK` / `201 Created`: Returns draft resource with updated `concurrency_token`, `updated_at`, and restored state.
  - `409 Conflict`: Concurrency token mismatch (stale tab).
    ```json
    {
      "error": "STALE_DRAFT_CONFLICT",
      "message": "This draft was modified from another tab or device.",
      "server_draft": {
        "id": 14,
        "concurrency_token": "f5e6d7...",
        "updated_at": "2026-11-17T02:40:00Z",
        "current_step": "options",
        "payload": { ... }
      }
    }
    ```

#### 2. Get Draft / Latest Draft
- **Endpoint:** `GET /api/partner/tours/drafts/latest` or `GET /api/partner/tours/{id}/drafts/latest`
- **Response:** `200 OK` with full draft snapshot and `concurrency_token`.

### 4.2 First-Save Idempotency (D2)
- Handled in `POST /api/partner/tours` (Tour Creation) and `POST /api/partner/tours/drafts`:
- Header or Payload: `Client-Creation-Id: <UUID>` or `creation_id: <UUID>`.
- Scoped to authenticated `partner_id`.
- **Behavior:**
  - If a tour or draft with the same `(partner_id, creation_id)` exists:
    - If payload fingerprint matches: return the existing entity with HTTP 200/201.
    - If payload fingerprint differs materially: return HTTP 409 Conflict (`CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD`).

### 4.3 Public Departures & Availability API (D3, D4, D5)
- **Endpoint:** `GET /api/public/tours/{slug}/departures`
- **Query Parameters:**
  - `option_id` (integer, optional)
  - `date_from` (YYYY-MM-DD, required)
  - `date_to` (YYYY-MM-DD, required, max window 60 days per query)
  - `participants` (integer, optional, default 1)
- **Response `200 OK`:**
  ```json
  {
    "data": [
      {
        "id": 1045,
        "tour_option_id": 12,
        "starts_at": "2026-12-01T08:00:00Z",
        "timezone": "Europe/Rome",
        "local_date": "2026-12-01",
        "local_time": "09:00:00",
        "capacity": 10,
        "available_spots": 4,
        "booking_cutoff_hours": 2,
        "is_bookable": true,
        "pricing": {
          "mode": "tiered_per_person",
          "tier_id": 5,
          "min_participants": 3,
          "max_participants": 4,
          "unit_price_cents": 800,
          "total_price_cents": 2400,
          "currency": "EUR"
        }
      }
    ]
  }
  ```

### 4.4 Booking Creation Contract (D4, D5)
- **Endpoint:** `POST /api/public/bookings`
- **Request Body (Additions):**
  ```json
  {
    "tour_slug": "florence-food-tour",
    "tour_date": "2026-12-01",
    "tour_option_id": 12,
    "tour_departure_id": 1045, // Explicit departure ID
    "participant_count": 3,
    "page_load_price": 800, // Expected per-person unit price in cents
    "locale": "es",
    "idempotency_key": "user-client-uuid-..."
  }
  ```
- **Backend Execution Rules:**
  1. Transaction lock order: `Partner` sharedLock -> `Tour` lockForUpdate -> `TourDeparture` lockForUpdate.
  2. Validate departure belongs to selected option and tour.
  3. Validate booking cutoff: `now() <= starts_at - cutoff_hours`.
  4. Validate participant count against departure remaining capacity:
     `remaining = departure.capacity - sum(valid pending + confirmed + completed bookings)`.
     Pending bookings only count if `pending_expires_at > now()`.
  5. Resolve pricing tier:
     Find `PricingTier` where `tour_option_id = option.id` and `min_participants <= count <= (max_participants ?? infinity)`.
     Read authoritative `price_amount` (cents).
     `total_price = unit_price * count`.
  6. Check price drift: if `page_load_price !== unit_price`, throw 409 `price_changed` returning authoritative price.
  7. Insert `Booking` referencing `tour_departure_id`, `pricing_tier_id`, and `pricing_snapshot`.

---

## 5. Pricing Tier Logic & Calculations (D5)

### Configuration Rules
1. `min_participants >= 1`.
2. `max_participants >= min_participants` or `null` for open-ended top tier.
3. No overlapping ranges for the same option.
4. Contiguous coverage across the advertised range `[1 .. option.group_size_max]`.
5. Prices must be non-negative integers in cents.

### Calculation Verification Table
| Participants in booking | Unit Price per Person | Total Calculation | Authoritative Total |
|---|---:|---|---:|
| **1** | €10.00 (1000¢) | 1 × €10.00 | **€10.00 (1000¢)** |
| **2** | €9.00 (900¢) | 2 × €9.00 | **€18.00 (1800¢)** |
| **3** | €8.00 (800¢) | 3 × €8.00 | **€24.00 (2400¢)** |
| **4** | €8.00 (800¢) | 4 × €8.00 | **€32.00 (3200¢)** |
| **5** | €7.00 (700¢) | 5 × €7.00 | **€35.00 (3500¢)** |
| **10** | €7.00 (700¢) | 10 × €7.00 | **€70.00 (7000¢)** |

---

## 6. Slices & Implementation Sequence

- **Slice S1 — Contract & Data Model:**
  - Create migrations: `add_egm005_draft_enhancements_table`, `create_tour_departures_table`, `add_egm005_fields_to_pricing_tiers_and_bookings`.
  - Eloquent models & relationships: `TourDeparture`, update `TourOption`, `PricingTier`, `TourDraft`, `Booking`.
- **Slice S2 — Draft Recovery & First-Save Idempotency (A2):**
  - Implement `TourDraftService` with concurrency token and cross-session resume.
  - Implement first-save UUID idempotency in `TourService`.
  - Frontend `tourWizard` Zustand store hydration and server draft synchronization.
- **Slice S3 — Explicit Departures & Availability (A3):**
  - Implement `TourDepartureService` (generation up to 180 days, DST safety, recurrence).
  - Public departure availability API (`/api/public/tours/{slug}/departures`).
- **Slice S4 — Participant-Count Tiered Pricing (A3):**
  - Implement `PricingTierService` (option-level validation, contiguous tier checks, unit price lookup).
  - Update public tour detail and departure endpoints to return authoritative tiered pricing.
- **Slice S5 — Booking, Checkout & Voucher Integration (A5):**
  - Update `CreateBookingAction` and `AvailabilityService` for departure-level independent capacity and tiered pricing snapshot.
  - Update `VoucherService` to format explicit departure datetime, timezone, and snapshot.
- **Slice S6 — Verification, Regressions & Goldfish Review:**
  - Run full Pest, Jest, PHPStan, Pint, and Playwright suites.
  - Independent 3-perspective Goldfish review.
