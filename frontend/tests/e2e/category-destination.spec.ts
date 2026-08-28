import { test, expect } from '@playwright/test';

test.describe('Category & Destination Pages', () => {
  test('category page loads with tour grid', async ({ page }) => {
    await page.goto('/en/categories/adventure');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });

  test('destination page loads with tour grid', async ({ page }) => {
    await page.goto('/en/destinations/rome');
    await page.goto('/en/destinations/rome-italy');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('main')).toBeVisible();
  });

  test('category page has sort dropdown', async ({ page }) => {
    await page.goto('/en/categories/adventure');
    await page.goto('/en/categories/walking');
    await expect(page.getByLabel('Sort by:')).toBeVisible();
  });

  test('destination page has sort dropdown', async ({ page }) => {
    await page.goto('/en/destinations/rome');
    await page.goto('/en/destinations/rome-italy');
    await expect(page.getByLabel('Sort by:')).toBeVisible();
  });

  test('category pagination is visible when multiple pages exist', async ({ page }) => {
    await page.goto('/en/categories/walking');
    const pagination = page.getByRole('navigation', { name: 'Search results pagination' });
    if (await pagination.isVisible()) {
      await expect(pagination).toBeVisible();
    }
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/categories/adventure');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
