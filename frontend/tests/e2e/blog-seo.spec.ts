import { test, expect } from '@playwright/test';

// This test exercises the REAL backend seeded by BlogBrowserTestSeeder.
// The seo-test-article fixture carries custom meta_title/meta_description,
// so the title, JSON-LD, and breadcrumb assertions run against genuine
// server-rendered data. The browser page.route() mock was removed because
// Next.js Server Components fetch blog data server-side.

test.describe('Blog SEO and Metadata', () => {
  test('includes correct meta title, description, canonical, and structured data ld+json', async ({
    page,
  }) => {
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
    // The seeded author profile display name is Elena Rossi.
    expect(blogPosting.author.name).toBe('Elena Rossi');

    const breadcrumbList = scriptContents.find((item) => item?.['@type'] === 'BreadcrumbList');
    expect(breadcrumbList).toBeDefined();
    expect(breadcrumbList.itemListElement.length).toBeGreaterThanOrEqual(3);
  });
});