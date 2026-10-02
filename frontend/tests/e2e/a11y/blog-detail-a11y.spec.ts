import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Blog Accessibility (a11y)', () => {
  test('blog detail page should not have automatically detectable accessibility violations', async ({
    page,
  }) => {
    await page.goto('/en/blog/hidden-gems-florence');
    await expect(page.getByRole('heading', { level: 1, name: 'Hidden Gems in Florence' })).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
