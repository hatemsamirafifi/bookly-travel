import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('booking page should not have accessibility violations', async ({ page }) => {
  await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-06-01&participants=2');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
