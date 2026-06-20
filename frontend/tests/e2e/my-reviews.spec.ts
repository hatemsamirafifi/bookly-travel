import { test, expect } from '@playwright/test';

test.describe('My Reviews', () => {
  // Auth is provided by the `-authed` Playwright projects via a shared
  // storageState (tests/e2e/auth.setup.ts logs in once). No per-test login —
  // that would re-hit the backend `auth` rate limiter (10/min/IP) and the old
  // `text=Sign Out` check failed because that action is in a collapsed dropdown.

  test('my reviews page renders', async ({ page }) => {
    await page.goto('/en/my-reviews');

    await expect(page.getByRole('heading', { name: /My Reviews/i })).toBeVisible();
  });

  test('my reviews shows empty state or list', async ({ page }) => {
    await page.goto('/en/my-reviews');

    // Either empty state or review list should be visible
    await expect(
      page.locator('text=share your experience').or(page.locator('article')).first()
    ).toBeVisible();
  });

  test('review card shows tour name and rating', async ({ page }) => {
    await page.goto('/en/my-reviews');

    const reviewCard = page.locator('article').first();
    if (await reviewCard.isVisible()) {
      await expect(reviewCard.locator('a')).toBeVisible();
      await expect(reviewCard.locator('text=/stars/')).toBeVisible();
    }
  });

  test('editable review shows edit button', async ({ page }) => {
    await page.goto('/en/my-reviews');

    const editBtn = page.locator('button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();
      await expect(page.getByRole('heading', { name: 'Edit Review' })).toBeVisible();
      await expect(page.getByRole('button', { name: /Update Review/i })).toBeVisible();
    }
  });

  test('review edit form can be cancelled', async ({ page }) => {
    await page.goto('/en/my-reviews');

    const editBtn = page.locator('button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();
      await expect(page.getByRole('heading', { name: 'Edit Review' })).toBeVisible();

      const cancelBtn = page.getByRole('button', { name: /Cancel/i });
      await cancelBtn.click();

      // Should return to review list view
      await expect(page.getByRole('heading', { name: 'Edit Review' })).not.toBeVisible();
    }
  });

  test('empty state links to search page', async ({ page }) => {
    await page.goto('/en/my-reviews');

    const browseLink = page.locator('a:has-text("Browse Tours")').first();
    if (await browseLink.isVisible()) {
      await browseLink.click();
      await page.waitForURL(/\/en\/search/);
      await expect(page.getByRole('heading', { name: /search/i })).toBeVisible();
    }
  });
});
