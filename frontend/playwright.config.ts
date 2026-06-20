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

// Specs that require an authenticated traveler. They run only in the
// `-authed` projects (which reuse the storageState saved by `setup`), and are
// excluded from the base (logged-out) projects so they don't also run
// unauthenticated and fail. The backend `auth` limiter is 10 logins/min/IP and
// all E2E tests share one IP, so per-test browser logins are removed from these
// specs — storageState authenticates them with a single login (see
// tests/e2e/auth.setup.ts).
// Matches only specs that sit directly under tests/e2e (the [\/]e2e[\/] before
// the filename excludes same-named specs in subdirectories — notably
// partner/profile.spec.ts, which needs partner auth, not the traveler
// storageState used here). A plain basename glob would wrongly pull in
// partner/profile.spec.ts; a `^filename$` regex matches nothing because
// Playwright matches against the full path (tests/e2e/...), not the basename.
const AUTHED_SPEC =
  /[\/]e2e[\/](my-bookings|booking-detail|cancel-booking|review-submission|my-reviews|profile|wishlist|checkout|payment|booking)\.spec\.ts$/;

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
  reporter: 'html',
  use: {
    baseURL,
    trace: 'on-first-retry',
  },
  projects: [
    // Authenticates once as the seeded traveler and saves the browser storage
    // state (auth_token in localStorage) to tests/.auth/traveler.json. The
    // `-authed` projects below depend on this and reuse the state, so each
    // test starts authenticated without a per-test login (the backend `auth`
    // limiter is 10 logins/min/IP and all tests share one IP).
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
    // ones, which are handled by the `-authed` projects below. Excluding
    // my-bookings here means it only runs authenticated (where it passes),
    // not a second time logged-out (where it can't).
    {
      name: 'chromium',
      testIgnore: [AUTHED_SPEC, /.*\.setup\.ts/],
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'mobile',
      testIgnore: [AUTHED_SPEC, /.*\.setup\.ts/],
      use: {
        ...devices['Pixel 7'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
    {
      name: 'a11y',
      testIgnore: [AUTHED_SPEC, /.*\.setup\.ts/],
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },

    // Authenticated projects — reuse the storage state saved by `setup` and
    // run only the specs that require a logged-in traveler.
    {
      name: 'chromium-authed',
      testMatch: AUTHED_SPEC,
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
      testMatch: AUTHED_SPEC,
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
      testMatch: AUTHED_SPEC,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/.auth/traveler.json',
        launchOptions: {
          args: ['--disable-gpu', '--no-sandbox', '--disable-setuid-sandbox'],
        },
      },
    },
  ],
});
