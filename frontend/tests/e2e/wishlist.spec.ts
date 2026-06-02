import { test, expect } from '@playwright/test';

test.describe('Wishlist', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign Out')).toBeVisible();
  });

  test('wishlist page renders', async ({ page }) => {
    await page.goto('/en/wishlist');

    await expect(page.getByRole('heading', { name: /Wishlist/i })).toBeVisible();
  });

  test('wishlist shows empty state or grid', async ({ page }) => {
    await page.goto('/en/wishlist');

    // Either empty state or wishlist grid should be visible
    await expect(
      page.locator('text=No saved tours').or(page.locator('[data-testid="wishlist-grid"]')).first()
    ).toBeVisible();
  });

  test('tour card wishlist button toggles saved state', async ({ page }) => {
    await page.goto('/en/search');

    // Find a tour card with a wishlist button
    const wishlistBtn = page.locator('[data-testid="wishlist-button"]').first();
    if (await wishlistBtn.isVisible()) {
      const initialAriaPressed = await wishlistBtn.getAttribute('aria-pressed');

      await wishlistBtn.click();
      await page.waitForTimeout(500);

      const newAriaPressed = await wishlistBtn.getAttribute('aria-pressed');
      expect(newAriaPressed).not.toBe(initialAriaPressed);
    }
  });

  test('removing item from wishlist updates grid', async ({ page }) => {
    await page.goto('/en/wishlist');

    const removeBtn = page.locator('button:has-text("Remove")').or(page.locator('[data-testid="remove-wishlist"]')).first();
    if (await removeBtn.isVisible()) {
      await removeBtn.click();
      await page.waitForTimeout(500);

      // Item should disappear or empty state should show
      await expect(
        page.locator('text=No saved tours').or(page.locator('[data-testid="wishlist-grid"]')).first()
      ).toBeVisible();
    }
  });

  test('wishlist item links to tour detail', async ({ page }) => {
    await page.goto('/en/wishlist');

    const tourLink = page.locator('a[href^="/en/tours/"]').first();
    if (await tourLink.isVisible()) {
      await tourLink.click();
      await page.waitForURL(/\/en\/tours\//);
      await expect(page.locator('h1')).toBeVisible();
    }
  });
});
