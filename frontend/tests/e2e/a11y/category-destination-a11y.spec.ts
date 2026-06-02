import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('category page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/categories/adventure');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});

test('destination page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/destinations/rome');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
