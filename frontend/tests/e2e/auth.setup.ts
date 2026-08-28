import { test as setup, expect } from '@playwright/test';

// Global authentication setup for the E2E suite.
//
// Logs in ONCE as the seeded traveler and saves the browser storage state
// (localStorage auth_token + cookies) to a file. Authed projects declare a
// dependency on this setup project and reuse the saved state, so each test
// starts already authenticated — no per-test browser login. This keeps the
// whole suite under the backend `auth` rate limiter (10/min in production;
// raised for local/docker via APP_ENV so the suite cannot trip it).
//
// Authed specs run only in the `-authed` projects (see playwright.config.ts);
// logged-out specs (auth, unauthenticated guard cases, …) keep running in the
// base projects without this storageState.
//
// Diagnostics contract: if the seeded account is missing or the login API
// fails, this setup FAILS LOUDLY with the HTTP status and response body
// instead of silently timing out on the localStorage poll.

const TEST_USER = { email: 'test@example.com', password: 'Password123!' };
export const STORAGE_STATE_PATH = 'tests/.auth/traveler.json';

setup('authenticate as traveler', async ({ page, context }) => {
  await context.addCookies([
    { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
  ]);

  await page.goto('/en/auth/login');

  // After a .next clear / container restart, the first compile may return 404
  // or the page may need extra time to hydrate. Wait for the form to appear
  // before proceeding.
  await page.locator('input[name="email"]').waitFor({ state: 'visible', timeout: 30000 });

  await page.fill('input[name="email"]', TEST_USER.email);
  await page.fill('input[name="password"]', TEST_USER.password);

  // Capture the login API call so failures surface with real diagnostics.
  // Created just before the click so the timeout doesn't expire during
  // page hydration.
  const loginResponsePromise = page.waitForResponse(
    (resp) => resp.url().includes('/api/public/auth/login'),
    { timeout: 30000 },
  );

  await page.click('button[type="submit"]');

  const loginResponse = await loginResponsePromise;
  if (!loginResponse.ok()) {
    const body = await loginResponse.text().catch(() => '<unreadable body>');
    throw new Error(
      `Traveler E2E login failed: HTTP ${loginResponse.status()} from ${loginResponse.url()}\n` +
        `Credentials used: ${TEST_USER.email}\n` +
        `Response body: ${body}\n` +
        'Hint: ensure the database is migrated and seeded in Docker:\n' +
        '  docker compose exec laravel php artisan migrate --force\n' +
        '  docker compose exec laravel php artisan db:seed --force',
    );
  }

  // Login succeeds once LoginForm's login() stores the auth token in
  // localStorage (which also drives navigation off /auth/login). The token is
  // the reliable signal — the `**/en**` URL glob would match the login page
  // itself (/en/auth/login) and resolve before login finished.
  await expect
    .poll(async () => page.evaluate(() => localStorage.getItem('auth_token')), {
      timeout: 15000,
    })
    .not.toBeNull();

  await page.context().storageState({ path: STORAGE_STATE_PATH });

  // Warm up dev-server route compilation for authenticated traveler pages so
  // the first authed test doesn't cold-compile and time out.
  const warmupPaths = ['/en/my-bookings', '/en/profile', '/en/wishlist', '/en/my-reviews'];
  for (const p of warmupPaths) {
    await page.goto(p).catch(() => {});
  }
});
