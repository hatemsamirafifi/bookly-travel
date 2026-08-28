import { test as setup, expect } from '@playwright/test';

// Partner authentication setup for the E2E suite.
//
// Logs in ONCE as the seeded partner (created by PartnerSeeder:
// partner@bookly.test / password, approved onboarding_status=complete,
// owner of the seeded tours/bookings/notifications) and saves the browser
// storage state to tests/.auth/partner.json. The `-partner` projects in
// playwright.config.ts depend on this setup project and reuse the state, so
// every partner dashboard test starts authenticated as a partner without any
// per-test browser login.
//
// This fixture is fully isolated from the traveler storage state
// (tests/.auth/traveler.json) — the two accounts never share a session.

const PARTNER_USER = { email: 'partner@bookly.test', password: 'password' };
export const PARTNER_STORAGE_STATE_PATH = 'tests/.auth/partner.json';

setup('authenticate as partner', async ({ page, context }) => {
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

  await page.fill('input[name="email"]', PARTNER_USER.email);
  await page.fill('input[name="password"]', PARTNER_USER.password);

  // Capture the login API call so failures surface with real diagnostics.
  const loginResponsePromise = page.waitForResponse(
    (resp) => resp.url().includes('/api/public/auth/login'),
    { timeout: 30000 },
  );

  await page.click('button[type="submit"]');

  const loginResponse = await loginResponsePromise;
  if (!loginResponse.ok()) {
    const body = await loginResponse.text().catch(() => '<unreadable body>');
    throw new Error(
      `Partner E2E login failed: HTTP ${loginResponse.status()} from ${loginResponse.url()}\n` +
        `Credentials used: ${PARTNER_USER.email}\n` +
        `Response body: ${body}\n` +
        'Hint: ensure the database is migrated and seeded in Docker:\n' +
        '  docker compose exec laravel php artisan migrate --force\n' +
        '  docker compose exec laravel php artisan db:seed --force',
    );
  }

  await expect
    .poll(async () => page.evaluate(() => localStorage.getItem('auth_token')), {
      timeout: 15000,
    })
    .not.toBeNull();

  // The user menu only renders for authenticated users; verifying it proves
  // the persisted token restores a working authenticated app state.
  await page.locator('button[aria-haspopup="menu"]').first().waitFor({
    state: 'visible',
    timeout: 20000,
  });

  await page.context().storageState({ path: PARTNER_STORAGE_STATE_PATH });

  // Warm up dev-server route compilation so the first partner test doesn't
  // cold-compile a heavy route (e.g. the tour-creation wizard) and exceed the
  // 30s test timeout. Errors are suppressed — we only need the compile side
  // effect, not a successful page load.
  const warmupPaths = [
    '/en/partner',
    '/en/partner/tours',
    '/en/partner/reviews',
    '/en/partner/bookings',
    '/en/partner/profile',
    '/en/partner/analytics',
    '/en/partner/tours/create',
  ];
  for (const p of warmupPaths) {
    await page.goto(p).catch(() => {});
  }
});
