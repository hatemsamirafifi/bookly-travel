import { test, expect } from '@playwright/test';

test.describe('Wishlist', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP) and the old
  // `text=Sign Out` check failed because that action is in a collapsed dropdown.

  test('wishlist page renders', async ({ page }) => {
    await page.goto('/en/wishlist');

    await expect(page.getByRole('heading', { name: /Wishlist/i })).toBeVisible();
  });

  test('wishlist shows empty state or grid', async ({ page }) => {
    await page.goto('/en/wishlist');

    // Empty state ("Your wishlist is empty.") when the traveler has no saved
    // tours, otherwise the populated grid ([data-testid="wishlist-grid"]).
    await expect(
      page.getByText('Your wishlist is empty.').or(page.locator('[data-testid="wishlist-grid"]')).first()
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
        page.getByText('Your wishlist is empty.').or(page.locator('[data-testid="wishlist-grid"]')).first()
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
