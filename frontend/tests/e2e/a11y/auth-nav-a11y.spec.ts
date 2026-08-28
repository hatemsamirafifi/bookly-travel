import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Auth Navigation Accessibility', () => {
  test('user menu dropdown has no accessibility violations', async ({ page }) => {
    await page.goto('/en');
    const menuBtn = page.locator('button[aria-haspopup="menu"]');
    await expect(menuBtn).toBeVisible();
    await menuBtn.click();
    await expect(page.locator('role=menu')).toBeVisible();

    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('mobile navigation panel has no accessibility violations', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en');
    const openMenuBtn = page.locator('button[aria-label="Open menu"]');
    await expect(openMenuBtn).toBeVisible();
    await openMenuBtn.click();
    await expect(page.locator('button[aria-label="Close menu"]')).toBeVisible();

    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
