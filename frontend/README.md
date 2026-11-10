# Bookly Frontend

<!-- EGM contract -->

## Overview

Next.js 16 storefront and partner frontend, with next-intl request configuration, Laravel API and media rewrites, and separate application and performance test entry points.

## Interface

- `package.json` defines `dev`, `build`, `start`, `lint`, `test`, `typecheck`, `test:e2e`, `test:a11y`, `test:performance-tools`, `lighthouse`, and `lhci`. [package.json](./package.json#L6)
- Next.js loads `src/i18n/request.ts` through the next-intl plugin. Jest uses jsdom and ts-jest, and Playwright selects its origin from `PLAYWRIGHT_BASE_URL`, the Docker nginx host, or the local nginx port. [next.config.ts](./next.config.ts#L5) [jest.config.mjs](./jest.config.mjs#L1) [playwright.config.ts](./playwright.config.ts#L16)
- [eslint.config.mjs](./eslint.config.mjs#L25) declares `default export`.
- [jest.config.mjs](./jest.config.mjs#L18) declares `default export`.
- Source file [jest.setup.ts](./jest.setup.ts).
- [lighthouserc.cjs](./lighthouserc.cjs#L1) declares `module.exports`.
- [lighthouserc.js](./lighthouserc.js#L5) declares `module.exports`.
- [next.config.ts](./next.config.ts#L58) declares `default export`.
- [playwright.config.ts](./playwright.config.ts#L45) declares `default export`.
- [postcss.config.mjs](./postcss.config.mjs#L7) declares `default export`.
- Source file [sentry.client.config.ts](./sentry.client.config.ts).
- Source file [sentry.server.config.ts](./sentry.server.config.ts).
- [scripts](./scripts/README.md) — Locale catalog parity checking and Lighthouse tooling, including Windows host resource checks and an opt-in pagefile configuration helper.
- [src](./src/README.md) — Next.js application source, including the request proxy, route modules, UI components, locale configuration, and shared client and server helpers.

## Invariants

- The frontend serves `/sitemap.xml` through the backend’s `/api/public/sitemap.xml` endpoint and rewrites API and storage requests to `API_INTERNAL_URL` or `http://nginx`. [next.config.ts](./next.config.ts#L36)
- Next Image allows HTTPS hosts and explicitly listed HTTP development hosts. Fetches to private upstream IPs require `ALLOW_PRIVATE_IMAGE_UPSTREAM=true`. [next.config.ts](./next.config.ts#L18)
- The Sentry client `beforeSend` hook removes the user IP, `Authorization` header, and named password, token, and card variables from stack frames. [sentry.client.config.ts](./sentry.client.config.ts#L6)

## Side Effects

- The rewrites forward sitemap, API, and storage requests to the configured backend origin. The Next image optimizer can fetch configured upstream images. [next.config.ts](./next.config.ts#L18) [next.config.ts](./next.config.ts#L36)
- Both Lighthouse CI configs set `temporary-public-storage` as their upload target. [lighthouserc.js](./lighthouserc.js#L37) [lighthouserc.cjs](./lighthouserc.cjs#L17)

## Verification

- From `frontend/`, run `npm test`.

<!-- End EGM contract -->

This is a [Next.js](https://nextjs.org) project bootstrapped with [`create-next-app`](https://nextjs.org/docs/app/api-reference/cli/create-next-app).

## Getting Started

First, run the development server:

```bash
npm run dev
# or
yarn dev
# or
pnpm dev
# or
bun dev
```

Open [http://localhost:3000](http://localhost:3000) with your browser to see the result.

You can start editing the page by modifying `src/app/[locale]/page.tsx`. The page auto-updates as you edit the file.

The [localized layout](./src/app/%5Blocale%5D/layout.tsx#L20) and [voucher layout](./src/app/v/layout.tsx#L14) load Plus Jakarta Sans using `next/font/google`.

## Learn More

To learn more about Next.js, take a look at the following resources:

- [Next.js Documentation](https://nextjs.org/docs) - learn about Next.js features and API.
- [Learn Next.js](https://nextjs.org/learn) - an interactive Next.js tutorial.

You can check out [the Next.js GitHub repository](https://github.com/vercel/next.js) - your feedback and contributions are welcome!

## Deploy on Vercel

The easiest way to deploy your Next.js app is to use the [Vercel Platform](https://vercel.com/new?utm_medium=default-template&filter=next.js&utm_source=create-next-app&utm_campaign=create-next-app-readme) from the creators of Next.js.

Check out our [Next.js deployment documentation](https://nextjs.org/docs/app/building-your-application/deploying) for more details.
