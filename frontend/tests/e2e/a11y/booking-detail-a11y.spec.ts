import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Booking Detail Accessibility', () => {
  test('booking detail page has no accessibility violations', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
