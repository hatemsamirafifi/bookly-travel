import { test, expect } from '@playwright/test';

test.describe('Traveler Dashboard Smoke', () => {
  test('full authenticated journey', async ({ page }) => {
    // Login
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign Out')).toBeVisible();

    // Dashboard
    await page.goto('/en/my-bookings');
    await expect(page.locator('text=My Bookings')).toBeVisible();

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
    await expect(page.getByRole('heading', { name: /Profile Settings/i })).toBeVisible();

    // Wishlist
    await page.goto('/en/wishlist');
    await expect(page.getByRole('heading', { name: /Wishlist/i })).toBeVisible();

    // My Reviews
    await page.goto('/en/my-reviews');
    await expect(page.getByRole('heading', { name: /My Reviews/i })).toBeVisible();

    // Logout
    await page.goto('/en');
    await page.click('button[aria-haspopup="menu"]');
    await page.locator('role=menuitem', { hasText: 'Sign Out' }).click();
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign In')).toBeVisible();
  });
});
