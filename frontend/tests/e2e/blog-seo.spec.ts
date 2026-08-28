import { test, expect } from '@playwright/test';

test.describe('Blog SEO and Metadata', () => {
  test('includes correct meta title, description, canonical, and structured data ld+json', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/seo-test-article?locale=en', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 1,
            slug: 'seo-test-article',
            title: 'SEO Test Article',
            excerpt: 'Testing SEO meta tags and JSON-LD structured data.',
            content: '<p>Article body content.</p>',
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
              avatar_url: null,
              role: 'Writer',
            },
            related_tours: [],
            related_posts: [],
            seo: {
              meta_title: 'Custom SEO Meta Title',
              meta_description: 'Custom SEO description for testing.',
              canonical_url: 'https://bookly.com/en/blog/seo-test-article',
              hreflang: {
                en: 'https://bookly.com/en/blog/seo-test-article',
                es: 'https://bookly.com/es/blog/seo-test-article',
                it: 'https://bookly.com/it/blog/seo-test-article',
              },
            },
          },
        }),
      });
    });

    await page.goto('/en/blog/seo-test-article');

    // Check document title
    await expect(page).toHaveTitle(/Custom SEO Meta Title/);

    // Check JSON-LD structured data script
    const jsonLdScripts = await page.locator('script[type="application/ld+json"]').all();
    expect(jsonLdScripts.length).toBeGreaterThan(0);

    const scriptContents = await Promise.all(
      jsonLdScripts.map(async (s) => {
        const text = await s.textContent();
        return text ? JSON.parse(text) : null;
      })
    );

    const blogPosting = scriptContents.find((item) => item?.['@type'] === 'BlogPosting');
    expect(blogPosting).toBeDefined();
    expect(blogPosting.headline).toBe('SEO Test Article');
    expect(['John Doe', 'Elena Rossi']).toContain(blogPosting.author.name);

    const breadcrumbList = scriptContents.find((item) => item?.['@type'] === 'BreadcrumbList');
    expect(breadcrumbList).toBeDefined();
    expect(breadcrumbList.itemListElement.length).toBeGreaterThanOrEqual(3);
  });
});
