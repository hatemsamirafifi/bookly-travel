import { test, expect } from '@playwright/test';

test.describe('Blog Index and Listing Page', () => {
  const mockBlogListResponse = {
    data: [
      {
        id: 1,
        slug: 'hidden-gems-florence',
        title: 'Hidden Gems in Florence',
        excerpt: 'Beyond the Uffizi: secret spots in Florence.',
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
      {
        id: 2,
        slug: 'top-10-gelato-spots',
        title: 'Top 10 Gelato Spots in Rome',
        excerpt: 'The creamiest gelato in Italy tested by foodies.',
        cover_image: 'https://images.unsplash.com/photo-1501443762994-82bd5dace89a',
        reading_time_minutes: 3,
        published_at: '2026-05-08T10:00:00Z',
        is_featured: false,
        primary_category: {
          id: 2,
          slug: 'food-and-wine',
          name: 'Food & Wine',
        },
        author: {
          id: 2,
          name: 'Marco Bianchi',
          avatar_url: null,
        },
      },
    ],
    meta: {
      current_page: 1,
      last_page: 2,
      per_page: 12,
      total: 14,
    },
  };

  const mockCategoryResponse = {
    category: {
      id: 1,
      slug: 'city-guides',
      name: 'City Guides',
      description: 'Comprehensive destination breakdowns and insider walks.',
    },
    posts: [mockBlogListResponse.data[0]],
    meta: {
      current_page: 1,
      last_page: 1,
      per_page: 12,
      total: 1,
    },
  };

  test('renders blog index with featured hero, article grid, and pagination', async ({
    page,
  }) => {
    await page.route('**/api/public/blog?locale=en&page=1&per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockBlogListResponse),
      });
    });

    await page.goto('/en/blog');

    // Page title and header
    await expect(page.locator('h1')).toContainText('Travel Insights & Guides');

    // Featured hero section exists
    await expect(page.getByText('Featured Story')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Hidden Gems in Florence' })).toBeVisible();

    // Blog card in grid
    await expect(page.getByRole('heading', { name: 'Top 10 Gelato Spots in Rome' })).toBeVisible();

    // Pagination controls exist
    const pagination = page.locator('nav[aria-label="Blog articles pagination"]');
    await expect(pagination).toBeVisible();
  });

  test('renders category filtered page with breadcrumbs and posts', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/category/city-guides?locale=en&page=1&per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify(mockCategoryResponse),
      });
    });

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
    await page.route('**/api/public/blog?locale=en&page=1&per_page=12', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 12,
            total: 0,
          },
        }),
      });
    });

    await page.goto('/en/blog');
    await expect(page.getByText('No articles found')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Browse Tours' })).toBeVisible();
  });
});
