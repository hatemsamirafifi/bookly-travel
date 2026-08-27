import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('My Bookings Accessibility', () => {
  test('my bookings page has no accessibility violations', async ({ page }) => {
    await page.goto('/en/my-bookings');
    await expect(page.locator('main, [role="main"], h1').first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('filter tabs are keyboard accessible', async ({ page }) => {
    await page.goto('/en/my-bookings');
    const firstTab = page.locator('button[role="tab"]').first();
    await firstTab.focus();
    await page.keyboard.press('Enter');
    await expect(firstTab).toHaveAttribute('aria-selected', 'true');
  });
});
