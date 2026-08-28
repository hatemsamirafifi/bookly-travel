import { test, expect } from '@playwright/test';

test.describe('Traveler Dashboard Smoke', () => {
  test('full authenticated journey', async ({ page }) => {
    // Login
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('button[aria-haspopup="menu"]').first()).toBeVisible();

    // Dashboard
    await page.goto('/en/my-bookings');
    await expect(page.getByRole('heading', { name: /My Bookings/i })).toBeVisible();

    // Booking detail
    const card = page.locator('a[href*="/my-bookings/BKO-"]').first();
    if (await card.isVisible()) {
      await card.click();
      await page.waitForURL('**/my-bookings/BKO-**');
      await expect(page.locator('text=Reference')).toBeVisible();

      // Cancel if eligible
      const cancelBtn = page.locator('button:has-text("Cancel Booking")');
      if (await cancelBtn.isVisible() && await cancelBtn.isEnabled()) {
        await cancelBtn.click();
        const keepBtn = page.locator('button:has-text("Keep Booking")');
        if (await keepBtn.isVisible()) {
          await keepBtn.click();
          await expect(page.locator('role=dialog')).not.toBeVisible();
        }
      }
    }

    // Profile
    await page.goto('/en/profile');
    await expect(page.getByRole('heading', { name: 'Profile Settings' })).toBeVisible();

    // Wishlist
    await page.goto('/en/wishlist');
    await expect(page.getByRole('heading', { name: /Wishlist/i, level: 1 })).toBeVisible();

    // My Reviews
    await page.goto('/en/my-reviews');
    await expect(page.getByRole('heading', { name: /My Reviews/i, level: 1 })).toBeVisible();

    // Logout
    await page.goto('/en');
    const userMenu = page.locator('button[aria-haspopup="menu"]');
    if (await userMenu.isVisible()) {
      await userMenu.click();
      await page.locator('role=menuitem', { hasText: 'Sign Out' }).click();
      await page.waitForURL('**/en**');
      await expect(page.getByRole('link', { name: /sign in/i })).toBeVisible();
    }
  });
});
