import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Profile Accessibility', () => {
  test('profile page has no accessibility violations', async ({ page }) => {
    await page.goto('/en/profile');
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
