# Data Model: Public Frontend Entities

**Feature**: 010-public-frontend | **Date**: 2026-05-19

All persistent data lives in the Laravel backend. The frontend defines **view models** (data shapes consumed by components) and **client-side state** (checkout session). No frontend database.

---

## 1. Tour Card

Compact representation used in listing grids, homepage features, and search results.

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `id` | `number` | no | Tour identifier (from API) |
| `slug` | `string` | no | URL-safe unique key for routing |
| `title` | `Record<Locale, string>` | no | Translated title (en, es, it) |
| `coverImage` | `string` | no | Primary image URL (R2 CDN) |
| `coverImageBlur` | `string` | yes | Base64 blur placeholder (progressive loading) |
| `pricePerPerson` | `Price` | no | `{ amount: number, currency: string }` |
| `averageRating` | `number` | no | 1.0–5.0 star rating |
| `totalReviews` | `number` | no | Count of completed-booking reviews |
| `location` | `Record<Locale, string>` | no | Translated city/region name |
| `duration` | `string` | no | Human-readable (e.g., "3 hours") |
| `category` | `{ slug: string, name: Record<Locale, string> }` | no | Category reference |

**Validation**: `averageRating` ∈ [1.0, 5.0], `pricePerPerson.amount` > 0, `slug` non-empty.

**Source**: `GET /api/tours` (search/listing), `GET /api/tours/category/{slug}`, `GET /api/tours/destination/{slug}`

---

## 2. Tour Detail

Full information for a single tour page.

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `id` | `number` | no | Tour identifier |
| `slug` | `string` | no | URL-safe unique key |
| `title` | `Record<Locale, string>` | no | Translated title |
| `description` | `Record<Locale, string>` | no | Full translated description |
| `highlights` | `Record<Locale, string[]>` | no | Translated bullet-point highlights |
| `inclusions` | `Record<Locale, string[]>` | yes | What's included (translated) |
| `exclusions` | `Record<Locale, string[]>` | yes | What's excluded (translated) |
| `meetingPoint` | `string` | no | Address or location description |
| `images` | `Image[]` | no | Gallery images (URL + alt text + blurhash) |
| `pricePerPerson` | `Price` | no | `{ amount: number, currency: string }` |
| `duration` | `string` | no | Human-readable duration |
| `category` | `{ slug: string, name: Record<Locale, string> }` | no | Category reference |
| `destination` | `{ slug: string, name: Record<Locale, string> }` | no | Destination reference |
| `averageRating` | `number` | no | 1.0–5.0 |
| `totalReviews` | `number` | no | Review count |
| `reviews` | `Review[]` | yes | Recent reviews (most recent first) |
| `availability` | `AvailabilitySlot[]` | no | Upcoming dates with pricing and capacity |

### Image

| Field | Type | Description |
|-------|------|-------------|
| `url` | `string` | R2 CDN URL |
| `alt` | `Record<Locale, string>` | Translated alt text |
| `blurDataURL` | `string` | Base64 blur placeholder |

### Review

| Field | Type | Description |
|-------|------|-------------|
| `id` | `number` | Review identifier |
| `author` | `string` | Reviewer display name |
| `rating` | `number` | 1.0–5.0 |
| `comment` | `string` | Review text |
| `createdAt` | `ISO 8601 string` | Submission date |

### AvailabilitySlot

| Field | Type | Description |
|-------|------|-------------|
| `date` | `ISO 8601 string` | Available date (YYYY-MM-DD) |
| `timeSlot` | `string` | Time label (e.g., "09:00 AM") |
| `pricePerPerson` | `Price` | Price for this date (may differ from base) |
| `remainingCapacity` | `number` | Spots still available |

**State transitions**: Detail page is read-only display. Availability changes are backend-driven (other travelers book). Frontend re-fetches on checkout entry.

**Source**: `GET /api/tours/{slug}`

---

## 3. Checkout Session

Client-side state only. Managed by Zustand with `persist` middleware (sessionStorage).

| Field | Type | Nullable | Description |
|-------|------|----------|-------------|
| `tourId` | `number` | no | Selected tour ID |
| `tourSlug` | `string` | no | For navigation back to detail |
| `tourTitle` | `string` | no | Display title (current locale) |
| `selectedDate` | `ISO 8601 string` | no | Chosen availability date |
| `selectedTimeSlot` | `string` | no | Chosen time slot |
| `participants` | `number` | no | Count (≥ 1) |
| `pricePerPerson` | `Price` | no | Locked price at selection time |
| `totalPrice` | `number` | no | `participants * pricePerPerson.amount` |
| `guestName` | `string` | yes | Pre-filled for auth users |
| `guestEmail` | `string` | yes | Pre-filled for auth users |
| `guestPhone` | `string` | yes | Pre-filled for auth users |
| `specialRequests` | `string` | yes | Optional notes |
| `currentStep` | `1 \| 2 \| 3` | no | Active checkout step |
| `paymentIntentId` | `string` | yes | Returned by backend after step 2 → 3 transition |
| `idempotencyKey` | `string` | yes | Generated client-side, sent with payment |

**Fields explicitly excluded from persistence**: `paymentIntentId`, card details (never stored), tokens.

**Validation**:
- `participants` ≥ 1 and ≤ `remainingCapacity` (re-validated on proceed)
- `selectedDate` must be in the future
- `guestEmail` must be valid email format
- All required fields must be filled before step transition

**State transitions**:
```
Step 1 (Select) → Step 2 (Details) → Step 3 (Payment) → Confirmation
     ↑                  ↑                   |               (terminal)
     └──── back ────────┘──── back ─────────┘
```
On booking completion: store is cleared. On expiry (availability taken): store is cleared and user redirected.

---

## 4. Booking Confirmation

Read-only display model returned after successful payment.

| Field | Type | Description |
|-------|------|-------------|
| `reference` | `string` | Unique booking reference number |
| `tourTitle` | `string` | Booked tour title |
| `date` | `string` | Booking date (formatted) |
| `timeSlot` | `string` | Time slot |
| `participants` | `number` | Total participants |
| `amount` | `Price` | Total amount charged |
| `guestName` | `string` | Traveler name |
| `guestEmail` | `string` | Contact email |
| `meetingPoint` | `string` | Where to meet |
| `createdAt` | `ISO 8601 string` | Booking timestamp |

**State**: Terminal. Confirmation page is a one-time display after payment; not persisted on frontend.

**Source**: `POST /api/bookings` response (payment step completion)

---

## 5. Design System Tokens

Configuration constants, not a runtime entity. Defined in `lib/design-tokens.ts`.

```typescript
// Colors
colors: {
  primary: '#0A2540',   // Navy — primary actions, headers
  accent: '#FFB800',    // Gold — CTAs, highlights
  background: '#F7F9FB', // Off-white — page backgrounds
  // Semantic tokens derived from these
}

// Typography
fontFamily: 'Inter, system-ui, sans-serif'
fontWeights: { regular: 400, medium: 500, semibold: 600, bold: 700 }
headings: { h1: '2.5rem/1.2', h2: '2rem/1.25', h3: '1.5rem/1.3', h4: '1.25rem/1.35' }

// Spacing
grid: 8 // base unit; all spacing multiples of 8px

// Border radius
borderRadius: { default: '12px', sm: '8px', lg: '16px', full: '9999px' }
```

Applied via Tailwind `theme.extend` in `tailwind.config.ts`. All UI components reference these tokens; no hardcoded values in components.

---

## 6. Auth Session

In-memory state derived from Sanctum cookie session. No client-side storage of tokens.

| Field | Type | Description |
|-------|------|-------------|
| `isAuthenticated` | `boolean` | Whether user has valid Sanctum session |
| `user` | `User \| null` | `{ id, name, email, phone }` |
| `isLoading` | `boolean` | Auth check in progress |

Managed by a lightweight auth context or Zustand store (non-persisted).

**Source**: `GET /api/auth/user` (cookie-based session verification), `POST /api/auth/login`, `POST /api/auth/register`
