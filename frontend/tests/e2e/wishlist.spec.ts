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
    await page.waitForLoadState('networkidle');

    // Find a tour card with a wishlist button
    const wishlistBtn = page.locator('[data-testid="wishlist-button"]').first();
    await expect(wishlistBtn).toBeVisible();
    await expect(wishlistBtn).toBeEnabled();
    const initialAriaPressed = await wishlistBtn.getAttribute('aria-pressed');
    const mutation = page.waitForResponse((response) =>
      response.url().includes('/api/public/traveler/wishlist') && ['POST', 'DELETE'].includes(response.request().method()),
    );
    await wishlistBtn.click();
    await expect(wishlistBtn).toHaveAttribute('aria-pressed', initialAriaPressed === 'true' ? 'false' : 'true');
    expect((await mutation).ok()).toBe(true);
    await page.reload();
    await expect(page.locator('[data-testid="wishlist-button"]').first()).toHaveAttribute(
      'aria-pressed', initialAriaPressed === 'true' ? 'false' : 'true',
    );
  });

  test('removing item from wishlist updates grid', async ({ page }) => {
    await page.goto('/en/wishlist');

    const grid = page.locator('[data-testid="wishlist-grid"]');
    const empty = page.getByText('Your wishlist is empty.');
    await expect(grid.or(empty).first()).toBeVisible();
    if (await empty.isVisible()) {
      await page.goto('/en/search?q=tour');
      await page.waitForLoadState('networkidle');
      const save = page.locator('[data-testid="wishlist-button"]').first();
      await expect(save).toBeVisible();
      await expect(save).toHaveAttribute('aria-pressed', 'false');
      const addition = page.waitForResponse((response) =>
        response.url().includes('/api/public/traveler/wishlist') && response.request().method() === 'POST',
      );
      await save.click();
      expect((await addition).ok()).toBe(true);
      await page.goto('/en/wishlist');
      await expect(grid).toBeVisible();
    }

    const initialCards = await grid.locator('article').count();
    const removeBtn = grid.getByRole('button', { name: /remove/i }).first();
    await expect(removeBtn).toBeVisible();
    const removal = page.waitForResponse((response) =>
      response.url().includes('/api/public/traveler/wishlist/') && response.request().method() === 'DELETE',
    );
    await removeBtn.click();
    expect((await removal).status()).toBe(204);
    if (initialCards === 1) {
      await expect(empty).toBeVisible();
    } else {
      await expect(grid.locator('article')).toHaveCount(initialCards - 1);
    }
  });

  test('wishlist item links to tour detail', async ({ page }) => {
    await page.goto('/en/wishlist');

    const grid = page.locator('[data-testid="wishlist-grid"]');
    const empty = page.getByText('Your wishlist is empty.');
    await expect(grid.or(empty).first()).toBeVisible();
    if (await grid.isVisible()) {
      const tourLink = grid.locator('a[href^="/en/tours/"]').first();
      await expect(tourLink).toBeVisible();
      await tourLink.click();
      await page.waitForURL(/\/en\/tours\//);
      await expect(page.locator('h1')).toBeVisible();
    } else {
      await expect(empty).toBeVisible();
    }
  });
});
