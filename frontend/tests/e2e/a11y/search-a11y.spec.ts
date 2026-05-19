import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('search page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/search?q=rome');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
