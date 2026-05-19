# Quickstart: Public Frontend Development

**Feature**: 010-public-frontend | **Date**: 2026-05-19

## Prerequisites

- **Node.js** ≥ 20.x
- **npm** ≥ 10.x
- **Laravel backend** running at `API_URL` (configured in `.env.local`)
- **Stripe account** (test mode for development)
- **Sentry account** (DSN for error monitoring — optional in dev)

## Environment Variables

Create `frontend/.env.local`:

```bash
# API
NEXT_PUBLIC_API_URL=http://localhost:8000/api

# Stripe (test mode)
NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_test_xxxxxxxxxxxx

# Sentry (optional in dev)
NEXT_PUBLIC_SENTRY_DSN=https://xxxxx@sentry.io/xxxxx

# App
NEXT_PUBLIC_APP_URL=http://localhost:3000
NEXT_PUBLIC_DEFAULT_LOCALE=en
```

## Setup

```bash
cd frontend
npm install
npm run dev          # Starts Next.js dev server on http://localhost:3000
```

The dev server supports hot reload. Locale routes are available at:
- `http://localhost:3000/en/`
- `http://localhost:3000/es/`
- `http://localhost:3000/it/`

## Key Commands

```bash
# Development
npm run dev              # Start dev server
npm run build            # Production build
npm run start            # Start production server

# Testing
npm test                 # Run Jest unit tests
npm run test:e2e         # Run Playwright e2e tests
npm run test:e2e:ui      # Run Playwright with UI mode
npm run test:a11y        # Run axe-core accessibility audit

# Linting & Formatting
npm run lint             # ESLint
npm run format           # Prettier

# Lighthouse (CI)
npm run lighthouse       # Run Lighthouse performance audit
```

## Translation Workflow

1. Add translation keys to `messages/en.json` (base locale)
2. Copy new keys to `messages/es.json` and `messages/it.json`
3. Translate values in each locale file
4. Use `useTranslations()` hook in components:

```typescript
import { useTranslations } from 'next-intl';

function Hero() {
  const t = useTranslations('home.hero');
  return <h1>{t('title')}</h1>;
}
```

## Stripe Test Cards

| Card Number | Result |
|-------------|--------|
| `4242 4242 4242 4242` | Success |
| `4000 0000 0000 0002` | Decline |
| `4000 0025 0000 3155` | 3D Secure required |

Use any future expiry date, any 3-digit CVC.

## Project Structure

```
frontend/src/
├── app/[locale]/        # All routed pages
│   ├── page.tsx         # Homepage
│   ├── tours/page.tsx   # Search/listing
│   ├── tours/[slug]/    # Tour detail
│   ├── checkout/        # Booking checkout
│   ├── confirmation/    # Booking confirmation
│   └── auth/            # Login, register
├── components/          # Reusable UI components
│   ├── ui/              # Design system primitives
│   ├── layout/          # Header, Footer, etc.
│   ├── home/            # Homepage sections
│   ├── search/          # Search & filters
│   ├── tour/            # Tour-specific components
│   ├── checkout/        # Checkout step components
│   └── shared/          # Cookie consent, SEO head
├── hooks/               # Data fetching hooks
├── services/            # API client functions
├── stores/              # Zustand stores
├── lib/                 # Utilities & design tokens
└── i18n/                # next-intl configuration
```

## Architecture Patterns

- **Pages** are server components by default (SSR/SSG). Interactive elements (forms, filters) are client components imported into pages.
- **Data fetching** uses TanStack Query in client components for API calls. Server components fetch directly in `async` page functions.
- **State** is local (useState) for form inputs, Zustand for checkout session, TanStack Query cache for server state. No global store for UI state.
- **Styling** uses Tailwind utility classes with design tokens from `tailwind.config.ts`. No CSS modules or styled-components.
- **API calls** go through `services/` functions that wrap `fetch` with error handling and type-safe responses.

## Running Tests

```bash
# Unit tests (Jest)
npm test

# E2E tests (Playwright)
npm run build && npm run start &
npm run test:e2e

# Accessibility audit (axe-core)
npm run test:a11y

# Performance (Lighthouse)
npx lighthouse http://localhost:3000/en/ --view
```
