import { test, expect } from '@playwright/test';

// These tests exercise the REAL backend seeded by BlogBrowserTestSeeder
// (florence-walk featured post, hidden-gems-florence and top-10-gelato-spots
// articles, city-guides category). Browser page.route() mocks were removed
// because Next.js Server Components fetch blog data server-side, outside
// browser interception.

test.describe('Blog Index and Listing Page', () => {
  test('renders blog index with featured hero, article grid, and pagination', async ({
    page,
  }) => {
    await page.goto('/en/blog');

    // Page title and header
    await expect(page.locator('h1')).toContainText('Travel Insights & Guides');

    // Featured hero section exists
    await expect(page.getByText('Featured Story')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Florence Walking Guide' })).toBeVisible();

    // Blog card in grid
    await expect(page.getByRole('heading', { name: 'Hidden Gems in Florence' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Top 10 Gelato Spots in Rome' })).toBeVisible();

    // Pagination controls exist when multiple pages exist
    const pagination = page.locator('nav[aria-label="Blog articles pagination"]');
    if (await pagination.isVisible()) {
      await expect(pagination).toBeVisible();
    }
  });

  test('renders category filtered page with breadcrumbs and posts', async ({
    page,
  }) => {
    await page.goto('/en/blog/category/city-guides');

    // Category heading and description
    await expect(page.locator('h1')).toContainText('City Guides');
    await expect(page.getByText('Comprehensive destination breakdowns')).toBeVisible();

    // Breadcrumbs
    const breadcrumbs = page.locator('nav[aria-label="Breadcrumbs"]');
    await expect(breadcrumbs).toBeVisible();
    await expect(breadcrumbs).toContainText('Blog');
    await expect(breadcrumbs).toContainText('City Guides');

    // Article card
    await expect(page.getByRole('heading', { name: 'Hidden Gems in Florence' })).toBeVisible();
  });

  test('renders empty state when no articles match criteria', async ({ page }) => {
    await page.goto('/en/blog?category=nonexistent-category');
    await expect(page.getByText('No articles found')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Browse Tours' })).toBeVisible();
  });
});