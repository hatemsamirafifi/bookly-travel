import type { Page } from '@playwright/test';

/**
 * Shared E2E login helpers.
 *
 * Auth persists across navigations via the bearer token stored in localStorage
 * (restored by useAuth.restoreSession() -> GET /api/public/auth/me), so a single
 * login at the start of a test keeps the session alive for subsequent page.goto()s.
 *
 * "Sign Out" lives inside a closed user-menu dropdown, so the logged-in indicator
 * we assert on is the user-menu button itself (button[aria-haspopup="menu"]).
 */

const PARTNER_CREDENTIALS = { email: 'partner@bookly.test', password: 'password' };
const TRAVELER_CREDENTIALS = { email: 'test@example.com', password: 'Password123!' };

async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/en/auth/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  // User-menu button only renders once authenticated
  await page.locator('button[aria-haspopup="menu"]').first().waitFor({ state: 'visible' });
}

export function partnerLogin(page: Page): Promise<void> {
  return login(page, PARTNER_CREDENTIALS.email, PARTNER_CREDENTIALS.password);
}

export function travelerLogin(page: Page): Promise<void> {
  return login(page, TRAVELER_CREDENTIALS.email, TRAVELER_CREDENTIALS.password);
}