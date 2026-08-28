import { test, expect } from '@playwright/test';

test.describe('Auth Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();
  });

  test('header shows user menu after login', async ({ page }) => {
    await page.goto('/en');
    await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();
  });

  test('dropdown opens and navigates to dashboard', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      await menuBtn.click();
      await expect(page.locator('role=menuitem', { hasText: 'Dashboard' })).toBeVisible();
      await page.locator('role=menuitem', { hasText: 'Dashboard' }).click();
      await page.waitForURL('**/en/my-bookings**');
    }
  });

  test('dropdown navigates to all traveler pages', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      const links = [
        { label: 'My Bookings', path: '/en/my-bookings' },
        { label: 'Wishlist', path: '/en/wishlist' },
        { label: 'My Reviews', path: '/en/my-reviews' },
        { label: 'Profile Settings', path: '/en/profile' },
      ];

      for (const link of links) {
        await page.goto('/en');
        await page.click('button[aria-haspopup="menu"]');
        await page.locator('role=menuitem', { hasText: link.label }).click();
        await page.waitForURL(`**${link.path}**`);
      }
    }
  });

  test('logout redirects to homepage', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    if (await menuBtn.isVisible()) {
      await menuBtn.click();
      await page.locator('role=menuitem', { hasText: 'Sign Out' }).click();
      await page.waitForURL('**/en**');
      await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();
    }
  });
});
