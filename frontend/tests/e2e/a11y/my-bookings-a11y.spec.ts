import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('My Bookings Accessibility', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/auth/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/en**');
  });

  test('my bookings page has no accessibility violations', async ({ page }) => {
    await page.goto('/en/my-bookings');
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
