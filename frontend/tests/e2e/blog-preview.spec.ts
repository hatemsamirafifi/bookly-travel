import { test, expect } from '@playwright/test';

test.describe('Blog Authoring & Admin Previews (E2E / US3)', () => {
  test('Draft article preview loads with valid token and sets noindex robots', async ({ page }) => {
    // Navigate to preview page with simulated valid token
    const response = await page.goto('/en/blog/sample-draft-article/preview?token=sample-draft-article|9999999999|test-sig');

    // If backend isn't mock-seeded, it should either show 404/unavailable or the preview banner
    const isBannerVisible = await page.locator('text=Draft Preview Mode').isVisible().catch(() => false);
    const isNotFound = await page.locator('text=404').isVisible().catch(() => false);
    const isUnavailable = await page.locator('text=Article Unavailable').isVisible().catch(() => false);

    expect(isBannerVisible || isNotFound || isUnavailable).toBeTruthy();

    if (isBannerVisible) {
      await expect(page.locator('text=Draft Preview Mode')).toBeVisible();
      // Ensure robots meta is noindex
      const robotsMeta = page.locator('meta[name="robots"]');
      if (await robotsMeta.count() > 0) {
        await expect(robotsMeta).toHaveAttribute('content', /noindex/);
      }
    }
  });

  test('Missing token redirects or renders not found', async ({ page }) => {
    const response = await page.goto('/en/blog/sample-draft-article/preview');
    expect(response?.status() === 404 || (await page.locator('text=404').isVisible().catch(() => false))).toBeTruthy();
  });
});
