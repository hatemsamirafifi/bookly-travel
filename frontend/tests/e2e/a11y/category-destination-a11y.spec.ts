import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('category page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/categories/adventure');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});

test('destination page should not have accessibility violations', async ({ page }) => {
  // Seeded destination fixture (derived from the Rome tours' location).
  // A nonexistent slug renders the app's error page, which is a separate
  // cosmetic issue — the accessibility contract applies to the real page.
  await page.goto('/en/destinations/rome-italy');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
