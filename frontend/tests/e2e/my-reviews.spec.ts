import { test, expect } from '@playwright/test';

test.describe('My Reviews', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
    await expect(page.locator('text=Sign Out')).toBeVisible();
  });

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
      await expect(page.locator('text=Edit Your Review')).toBeVisible();
      await expect(page.getByRole('button', { name: /Update Review/i })).toBeVisible();
    }
  });

  test('review edit form can be cancelled', async ({ page }) => {
    await page.goto('/en/my-reviews');

    const editBtn = page.locator('button:has-text("Edit")').first();
    if (await editBtn.isVisible()) {
      await editBtn.click();
      await expect(page.locator('text=Edit Your Review')).toBeVisible();

      const cancelBtn = page.getByRole('button', { name: /Cancel/i });
      await cancelBtn.click();

      // Should return to review list view
      await expect(page.locator('text=Edit Your Review')).not.toBeVisible();
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
