import { test as setup, expect } from '@playwright/test';

// Global authentication setup for the E2E suite.
//
// Logs in ONCE as the seeded traveler and saves the browser storage state
// (localStorage auth_token + cookies) to a file. Authed projects declare a
// dependency on this setup project and reuse the saved state, so each test
// starts already authenticated — no per-test browser login. This keeps the
// whole suite under the backend `auth` rate limiter (10 logins/min/IP), which
// 18 concurrent per-test logins would otherwise blow past.
//
// Authed specs run only in the `-authed` projects (see playwright.config.ts);
// logged-out specs (auth, auth-guards, …) keep running in the base projects
// without this storageState.

const TEST_USER = { email: 'test@example.com', password: 'Password123!' };
export const STORAGE_STATE_PATH = 'tests/.auth/traveler.json';

setup('authenticate as traveler', async ({ page }) => {
  await page.goto('/en/auth/login');
  await page.fill('input[name="email"]', TEST_USER.email);
  await page.fill('input[name="password"]', TEST_USER.password);
  await page.click('button[type="submit"]');

  // Login succeeds once LoginForm's login() stores the auth token in
  // localStorage (which also drives navigation off /auth/login). The token is
  // the reliable signal — the `**/en**` URL glob would match the login page
  // itself (/en/auth/login) and resolve before login finished.
  await expect.poll(
    async () => page.evaluate(() => localStorage.getItem('auth_token')),
    { timeout: 15000 },
  ).not.toBeNull();

  await page.context().storageState({ path: STORAGE_STATE_PATH });
});