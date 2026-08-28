import { defineConfig, devices } from '@playwright/test';

// Browser host selection:
// - DOCKER_ENV=true: the Chromium browser runs INSIDE the bookly-frontend container
//   and must browse the app through the nginx reverse proxy (http://nginx) so the
//   page and /api share one origin — this avoids CORS, which otherwise blocks every
//   API call (the app's NEXT_PUBLIC_API_URL is http://nginx). nginx also proxies the
//   Next dev server's HMR WebSocket; the dev server accepts that origin via
//   `allowedDevOrigins` in next.config.ts. Browsing the dev server directly
//   (localhost:3000 or nextjs:3000) is wrong: localhost:3000 puts the API
//   cross-origin (CORS-blocked), and nextjs:3000 makes the HMR handshake fail
//   (ERR_INVALID_HTTP_RESPONSE) so pages never hydrate and input[name="email"]
//   never appears.
// - CI: GitHub Actions runner (no docker network) — kept on the service hostname.
// - local host dev (no Docker, no CI): localhost:8080 is the nginx reverse proxy.
const isDockerContainer = process.env.DOCKER_ENV === 'true';
const isCI = !!process.env.CI;
const baseURL = isDockerContainer
  ? 'http://nginx'
  : isCI
    ? 'http://nextjs:3000'
    : 'http://localhost:8080';

// All accessibility (a11y) spec files
const ALL_A11Y = /[\/]e2e[\/]a11y[\/].*\.spec\.ts$/;

// Specs that require an authenticated TRAVELER. They run only in the
// `-authed` projects (which reuse the storageState saved by `setup`), and are
// excluded from the base (logged-out) projects so they don't also run
// unauthenticated and fail.
const TRAVELER_AUTHED =
  /[\/]e2e[\/](my-bookings|booking-detail|cancel-booking|review-submission|my-reviews|profile|wishlist|checkout|payment|booking)\.spec\.ts$/;

// Traveler a11y specs
const TRAVELER_A11Y =
  /[\/]e2e[\/]a11y[\/](auth-nav|booking-detail|cancel-booking|my-bookings|my-reviews|profile|wishlist|checkout|payment)-a11y\.spec\.ts$/;

// Specs that require an authenticated PARTNER. They run only in the
// `-partner` projects, which reuse the storage state saved by
// tests/e2e/partner.setup.ts (seeded partner@bookly.test).
const PARTNER_AUTHED =
  /[\/]e2e[\/]partner[\/][^\/]*\.spec\.ts$/;

// Partner a11y specs
const PARTNER_A11Y =
  /[\/]e2e[\/]a11y[\/]partner-a11y\.spec\.ts$/;

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  // In the Docker container the browser runs against the Next dev server, so
  // every route cold-compiles on first visit. With the default worker count
  // (CPU cores) many routes compile concurrently and queue on the dev server's
  // single thread, blowing the 5s expect timeout on otherwise-correct pages.
  // Serial workers let each route compile once and stay warm for later tests.
  workers: process.env.CI || isDockerContainer ? 1 : undefined,
  // Dev-mode cold compiles (frontend route + first backend API call) can take
  // longer than Playwright's 5s default on the first visit of a route, which
  // made correct pages time out before hydrating. 20s tolerates the cold visit
  // without masking genuinely-missing elements for long.
  expect: { timeout: isDockerContainer ? 20000 : 5000 },
  // Dev-mode cold compiles can exceed the default 30s per-test timeout when a
  // test's beforeEach navigates to one route and the test body to another.
  // 60s tolerates the double-cold-compile without masking genuinely-hung tests.
  timeout: isDockerContainer ? 60000 : 30000,
  reporter: 'html',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  projects: [
    // Authenticates ONCE as the seeded traveler AND as the seeded partner
    // (runs both *.setup.ts files) and saves their browser storage states to
    // tests/.auth/traveler.json and tests/.auth/partner.json. The authed
    // projects below depend on this setup project and reuse those states, so
    // no test performs its own login.
    {
      name: 'setup',
      testMatch: /.*\.setup\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },

    // Base (logged-out) projects — run every spec EXCEPT the authenticated
    // traveler/partner ones, which are handled by the `-authed`/`-partner`
    // projects below, and a11y specs which run in a11y project.
    {
      name: 'chromium',
      testIgnore: [ALL_A11Y, TRAVELER_AUTHED, PARTNER_AUTHED, /.*\.setup\.ts/],
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'mobile',
      testIgnore: [ALL_A11Y, TRAVELER_AUTHED, PARTNER_AUTHED, /.*\.setup\.ts/],
      use: {
        ...devices['Pixel 7'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'a11y',
      testMatch: ALL_A11Y,
      testIgnore: [TRAVELER_A11Y, PARTNER_A11Y],
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },

    // Authenticated TRAVELER projects — reuse the storage state saved by
    // auth.setup.ts and run only the specs that require a logged-in traveler.
    {
      name: 'chromium-authed',
      testMatch: TRAVELER_AUTHED,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/traveler.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'mobile-authed',
      testMatch: TRAVELER_AUTHED,
      dependencies: ['setup'],
      use: {
        ...devices['Pixel 7'],
        storageState: 'tests/.auth/traveler.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'a11y-authed',
      testMatch: TRAVELER_A11Y,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/traveler.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },

    // Authenticated PARTNER projects — reuse the storage state saved by
    // partner.setup.ts and run only the specs under tests/e2e/partner/ plus
    // the partner a11y spec.
    {
      name: 'chromium-partner',
      testMatch: PARTNER_AUTHED,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/partner.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'mobile-partner',
      testMatch: PARTNER_AUTHED,
      dependencies: ['setup'],
      use: {
        ...devices['Pixel 7'],
        storageState: 'tests/.auth/partner.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'a11y-partner',
      testMatch: PARTNER_A11Y,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/partner.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
  ],
});
