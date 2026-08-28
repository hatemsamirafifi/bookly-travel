import { test, expect } from '@playwright/test';

test.describe('Blog Detail Page', () => {
  test('renders article title, author byline, and body content', async ({ page }) => {
    // Mock public blog post detail
    await page.route('**/api/public/blog/hidden-gems-florence?locale=en', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            slug: 'hidden-gems-florence',
            title: 'Hidden Gems in Florence',
            excerpt: 'Beyond the Uffizi: secret spots in Florence.',
            content: '<p>Florence is full of hidden wonders.</p><h2>The Oltrarno district</h2><p>Stroll through the artisan streets.</p>',
            cover_image: 'https://images.unsplash.com/photo-1543429776-2782fc8e1acd',
            reading_time_minutes: 5,
            published_at: '2026-05-10T10:00:00Z',
            updated_at: '2026-05-10T10:00:00Z',
            primary_category: {
              id: 1,
              slug: 'city-guides',
              name: 'City Guides',
            },
            author: {
              id: 1,
              name: 'Elena Rossi',
              avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
              role: 'Senior Travel Writer',
              bio: 'Passionate about Italian history and local art.',
            },
            related_tours: [
              {
                id: 101,
                slug: 'florence-artisan-walk',
                title: 'Florence Artisan Workshop Tour',
                price: { amount: 4500, currency: 'EUR', formatted: '€45.00' },
                duration: { minutes: 120, formatted: '2 hours' },
                cover_image_url: 'https://images.unsplash.com/photo-1543429776-2782fc8e1acd',
                rating: { average: 4.9, count: 28 },
                location: 'Florence, Italy',
              },
            ],
            related_posts: [
              {
                id: 2,
                slug: 'top-10-gelato-spots',
                title: 'Top 10 Gelato Spots',
                excerpt: 'The creamiest gelato in Italy.',
                cover_image: 'https://images.unsplash.com/photo-1501446529957-6226bd447c46',
                reading_time_minutes: 3,
                published_at: '2026-05-08T09:00:00Z',
                primary_category: {
                  id: 2,
                  slug: 'food-and-wine',
                  name: 'Food & Wine',
                },
                author: {
                  id: 1,
                  name: 'Elena Rossi',
                  avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
                  role: 'Senior Travel Writer',
                },
              },
            ],
            seo: {
              meta_title: 'Hidden Gems in Florence | Bookly Travel Insights',
              meta_description: 'Beyond the Uffizi: discover secret spots in Florence with our guide.',
              canonical_url: 'https://bookly.com/en/blog/hidden-gems-florence',
              hreflang: {
                en: 'https://bookly.com/en/blog/hidden-gems-florence',
                es: 'https://bookly.com/es/blog/hidden-gems-florence',
                it: 'https://bookly.com/it/blog/hidden-gems-florence',
              },
            },
          },
        }),
      });
    });

    await page.goto('/en/blog/hidden-gems-florence');

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Hidden Gems in Florence');
    await expect(page.getByText('Elena Rossi').first()).toBeVisible();
    await expect(page.getByText(/min read/).first()).toBeVisible();
    await expect(page.getByText('Florence is full of hidden wonders.')).toBeVisible();
    await expect(page.getByText('The Oltrarno district')).toBeVisible();
    await expect(page.getByText(/Top 10 Gelato Spots/i)).toBeVisible();
  });

  test('displays 410 Gone page for archived published article', async ({ page }) => {
    await page.route('**/api/public/blog/archived-guide?locale=en', async (route) => {
      await route.fulfill({
        status: 410,
        contentType: 'application/json',
        body: JSON.stringify({
          message: 'This article has been permanently archived.',
        }),
      });
    });

    await page.goto('/en/blog/archived-guide');
    await expect(page.getByRole('heading', { name: /Article Removed/i })).toBeVisible();
  });
});
