import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('tour detail page should not have accessibility violations', async ({ page }) => {
  // Seeded tour fixture: a nonexistent slug renders the app's error page
  // (no lang/main landmarks), which is a separate cosmetic issue — the
  // accessibility contract applies to the real tour detail page.
  await page.goto('/en/tours/hidden-gems-rome-walking-tour');
  const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
  expect(accessibilityScanResults.violations).toEqual([]);
});
