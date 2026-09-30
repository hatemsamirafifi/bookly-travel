import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

for (const locale of ['en', 'es', 'it']) {
  test(`${locale} tour detail with loaded reviews should not have accessibility violations`, async ({ page, request }) => {
    const slug = 'hidden-gems-rome-walking-tour';
    const response = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    expect(data.length).toBeGreaterThan(0);
    await page.goto(`/${locale}/tours/${slug}`);
    await expect(page.locator('main#main-content')).toBeVisible();
    // Wait for real API-backed review text, not the initial loading placeholders.
    await expect(page.locator('#reviews').getByText(data[0].reviewer_name, { exact: true }).first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
}
