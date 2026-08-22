import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test.describe('Blog Accessibility (a11y)', () => {
  test('blog detail page should not have automatically detectable accessibility violations', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/a11y-article?locale=en', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            slug: 'a11y-article',
            title: 'Accessible Travel Guide',
            excerpt: 'How to make travel accessible for everyone.',
            content: '<p>Accessibility in travel matters.</p><h2>Top destinations</h2><p>Here are accessible destinations.</p>',
            cover_image: 'https://images.unsplash.com/photo-1543429776-2782fc8e1acd',
            reading_time_minutes: 4,
            published_at: '2026-05-10T10:00:00Z',
            updated_at: '2026-05-10T10:00:00Z',
            primary_category: {
              id: 1,
              slug: 'guides',
              name: 'Guides',
            },
            author: {
              id: 1,
              name: 'John Doe',
              avatar_url: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb',
              role: 'Traveler',
              bio: 'Writing accessible guides.',
            },
            related_tours: [],
            related_posts: [],
            seo: {
              meta_title: 'Accessible Travel Guide | Bookly',
              meta_description: 'How to make travel accessible for everyone.',
              canonical_url: 'https://bookly.com/en/blog/a11y-article',
              hreflang: {
                en: 'https://bookly.com/en/blog/a11y-article',
                es: 'https://bookly.com/es/blog/a11y-article',
                it: 'https://bookly.com/it/blog/a11y-article',
              },
            },
          },
        }),
      });
    });

    await page.goto('/en/blog/a11y-article');
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
});
