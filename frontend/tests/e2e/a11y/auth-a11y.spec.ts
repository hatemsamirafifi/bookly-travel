import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Auth Accessibility', () => {
  test('login page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/auth/login');
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('register page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/auth/register');
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
