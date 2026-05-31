# Quickstart: Tour Management

## Prerequisites

- Node.js 20+ with pnpm (or npm/yarn)
- Running Laravel backend with Sanctum authentication
- Phase 1 frontend (spec 010) already initialized and configured

## Environment Variables

Ensure `.env.local` contains:

```env
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
NEXT_PUBLIC_APP_URL=http://localhost:3000
```

## Install Dependencies

```bash
cd frontend
pnpm install
```

No new dependencies are strictly required for Tour Management if Phase 1 already includes:
- `next`
- `react` / `react-dom`
- `tailwindcss`
- `lucide-react` (icons)
- `@formatjs/intl` or `next-intl` (i18n)

If not present, add:

```bash
pnpm add lucide-react
# If using next-intl for localization:
pnpm add next-intl
```

## Start Development Server

```bash
pnpm dev
```

The app runs at `http://localhost:3000`.

## Run Tests

```bash
# Unit + integration
pnpm test

# E2E (Playwright)
pnpm test:e2e
```

## Auth Setup for Local Testing

1. Ensure the Laravel backend is running and seeded with at least:
   - One published tour
   - One traveler account (email + password)
   - At least one booking for that traveler

2. Log in via `/en/auth/login` to establish a Sanctum session.

3. Navigate to authenticated routes:
   - `/en/my-bookings`
   - `/en/profile`
   - `/en/wishlist`
   - `/en/my-reviews`

## New Routes Checklist

After implementing Tour Management, these routes should resolve:

| Route | Page |
|-------|------|
| `/{locale}/my-bookings` | Dashboard |
| `/{locale}/my-bookings/{reference}` | Booking Detail |
| `/{locale}/profile` | Profile & Preferences |
| `/{locale}/wishlist` | Wishlist |
| `/{locale}/my-reviews` | My Reviews |

## Design Tokens

Reuse existing Phase 1 tokens:

| Token | Value | Usage |
|-------|-------|-------|
| Navy | `#0A2540` | Primary text, headings |
| Gold | `#FFB800` | CTAs, status accents, star ratings |
| Inter | `font-family: Inter` | All typography |
| Grid | `8px` base | Spacing scale |
| Radius | `12px` | Cards, buttons, modals |

## i18n Keys Required

Add these keys to your translation files (`en`, `es`, `it`):

- `nav.dashboard`, `nav.myBookings`, `nav.wishlist`, `nav.myReviews`, `nav.profileSettings`
- `booking.status.confirmed`, `booking.status.completed`, `booking.status.cancelled`
- `booking.cancel.title`, `booking.cancel.warning`, `booking.cancel.confirm`, `booking.cancel.keep`
- `profile.saveChanges`, `profile.changePassword`, `profile.preferences`
- `wishlist.empty.title`, `wishlist.empty.cta`
- `errors.generic`, `errors.tryAgain`

## Accessibility Checklist

Before marking Tour Management complete, verify:

- [ ] All filter tabs are keyboard-navigable (`Tab` + `Enter`)
- [ ] Modal traps focus and closes on `Escape`
- [ ] Form fields have associated `<label>` elements
- [ ] Color contrast ratios meet WCAG 2.1 AA (Navy on white, Gold on Navy)
- [ ] Screen reader announces status changes (toast, booking status update)
- [ ] Mobile touch targets are ≥ 44×44px (heart icon, buttons)
