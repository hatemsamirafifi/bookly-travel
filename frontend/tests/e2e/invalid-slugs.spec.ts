import { test, expect } from '@playwright/test';

// Invalid-slug / error-page gate: unknown tour, article, and category slugs
// plus a completely unmatched route must all render the localized 404 UI
// (the `[locale]/not-found.tsx` boundary) — never a blank screen, a Next.js
// error digest, or an unhandled stack trace.

const INVALID_ROUTES = [
  '/en/tours/non-existent-tour-xyz',
  '/en/blog/non-existent-article',
  '/en/categories/non-existent-category',
  '/en/destinations/non-existent-destination',
  '/en/completely-invalid-page',
] as const;

test.describe('Invalid slugs render the localized 404 page', () => {
  for (const route of INVALID_ROUTES) {
    test(`${route} returns 404 with localized 404 UI`, async ({ page }) => {
      const response = await page.goto(route);
      expect(response?.status(), `${route} should respond 404`).toBe(404);

      // Localized 404 UI from `[locale]/not-found.tsx`.
      await expect(page.getByRole('heading', { name: '404' })).toBeVisible();
      await expect(page.getByText('Page Not Found')).toBeVisible();
      await expect(
        page.getByText('The page you are looking for does not exist or has been moved.')
      ).toBeVisible();

      // Recovery links are present and point at the English locale home/search.
      await expect(page.getByRole('link', { name: 'Go Home' })).toHaveAttribute('href', '/en');
      await expect(page.getByRole('link', { name: 'Browse Tours' })).toHaveAttribute(
        'href',
        '/en/search'
      );

      // No blank screen, Next.js error boundary, or stack trace leaks.
      const bodyText = (await page.textContent('body')) ?? '';
      expect(bodyText.trim().length).toBeGreaterThan(0);
      expect(bodyText).not.toMatch(/Application error/i);
      expect(bodyText).not.toMatch(/Digest:/);
      expect(bodyText).not.toMatch(/at .*\(.*:\d+:\d+\)/);
    });
  }
});
