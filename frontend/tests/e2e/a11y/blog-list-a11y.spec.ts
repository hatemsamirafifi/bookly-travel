import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Blog Listing Accessibility (a11y)', () => {
  test('blog index page should not have automatically detectable accessibility violations', async ({
    page,
  }) => {
    await page.route('**/api/public/blog?locale=en&page=1&per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              slug: 'accessible-florence',
              title: 'Accessible Florence',
              excerpt: 'Guide to exploring Florence with ease.',
              cover_image: 'https://images.unsplash.com/photo-1543429776-2782fc8e1acd',
              reading_time_minutes: 5,
              published_at: '2026-05-10T10:00:00Z',
              is_featured: true,
              primary_category: {
                id: 1,
                slug: 'city-guides',
                name: 'City Guides',
              },
              author: {
                id: 1,
                name: 'Elena Rossi',
                avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
              },
            },
          ],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      });
    });

    await page.goto('/en/blog');
    await page.waitForLoadState('networkidle');

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });

  test('blog category page should not have automatically detectable accessibility violations', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/category/city-guides?locale=en&page=1&per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          category: {
            id: 1,
            slug: 'city-guides',
            name: 'City Guides',
            description: 'Comprehensive guides for city breaks.',
          },
          posts: [
            {
              id: 1,
              slug: 'accessible-florence',
              title: 'Accessible Florence',
              excerpt: 'Guide to exploring Florence with ease.',
              cover_image: 'https://images.unsplash.com/photo-1543429776-2782fc8e1acd',
              reading_time_minutes: 5,
              published_at: '2026-05-10T10:00:00Z',
              is_featured: false,
              primary_category: {
                id: 1,
                slug: 'city-guides',
                name: 'City Guides',
              },
              author: {
                id: 1,
                name: 'Elena Rossi',
                avatar_url: null,
              },
            },
          ],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 1,
          },
        }),
      });
    });

    await page.goto('/en/blog/category/city-guides');
    await page.waitForLoadState('networkidle');

    const accessibilityScanResults = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
