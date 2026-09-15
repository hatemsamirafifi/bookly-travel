import { test, expect } from '@playwright/test';

// These tests exercise the REAL backend seeded by BlogBrowserTestSeeder
// (hidden-gems-florence, archived-guide, florence-walk, top-10-gelato-spots,
// seo-test-article). Next.js Server Components fetch blog data server-side,
// so browser-side page.route() interception can never see those requests;
// the previous dead mock payloads were removed and every assertion now runs
// against genuine server-rendered fixture data.

test.describe('Blog Detail Page', () => {
  test('renders article title, author byline, and body content', async ({ page }) => {
    await page.goto('/en/blog/hidden-gems-florence');

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Hidden Gems in Florence');
    await expect(page.getByText('Elena Rossi').first()).toBeVisible();
    await expect(page.getByText(/min read/).first()).toBeVisible();
    await expect(page.getByText('Florence is full of hidden wonders.')).toBeVisible();
    await expect(page.getByText('The Oltrarno district')).toBeVisible();
    await expect(page.getByText(/Top 10 Gelato Spots/i)).toBeVisible();
  });

  test('displays 410 Gone page for archived published article', async ({ page }) => {
    // The archived-guide fixture is seeded with status=archived; the public
    // blog API returns 410 and the route renders the removal notice.
    await page.goto('/en/blog/archived-guide');
    await expect(page.getByRole('heading', { name: /Article Removed/i })).toBeVisible();
  });
});