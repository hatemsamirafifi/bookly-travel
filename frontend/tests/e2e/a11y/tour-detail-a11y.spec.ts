import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('tour detail page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/tours/test-adventure');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
