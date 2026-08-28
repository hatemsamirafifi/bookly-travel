import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Cancel Booking Accessibility', () => {
  test('cancel modal has no accessibility violations', async ({ page }) => {
    await page.goto('/en/my-bookings/BKO-TEST01');

    const cancelBtn = page.locator('button:has-text("Cancel Booking")');
    if (await cancelBtn.isVisible() && await cancelBtn.isEnabled()) {
      await cancelBtn.click();
      await expect(page.locator('role=dialog')).toBeVisible();

      const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
      expect(accessibilityScanResults.violations).toEqual([]);
    }
  });
});
