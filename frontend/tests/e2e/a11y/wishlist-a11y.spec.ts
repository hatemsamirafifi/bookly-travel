import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Wishlist Accessibility', () => {
  test('wishlist page has no accessibility violations', async ({ page }) => {
    await page.goto('/en/wishlist');
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
