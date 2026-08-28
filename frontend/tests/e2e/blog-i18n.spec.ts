import { test, expect } from '@playwright/test';

test.describe('Blog Multi-Language i18n Verification', () => {
  const mockEnPost = {
    id: 1,
    slug: 'florence-walk',
    title: 'Florence Walking Guide',
    excerpt: 'Best routes in Florence.',
    content: '<p>Enjoy Florence.</p>',
    reading_time_minutes: 4,
    published_at: '2026-05-10T10:00:00Z',
    updated_at: '2026-05-10T10:00:00Z',
    primary_category: { id: 1, slug: 'city-guides', name: 'City Guides' },
    author: { id: 1, name: 'Elena Rossi', role: 'Travel Writer', bio: 'Bio' },
    related_tours: [],
    related_posts: [],
  };

  const mockEsPost = {
    id: 1,
    slug: 'florence-walk',
    title: 'Guía a Pie de Florencia',
    excerpt: 'Las mejores rutas de Florencia.',
    content: '<p>Disfruta de Florencia.</p>',
    reading_time_minutes: 4,
    published_at: '2026-05-10T10:00:00Z',
    updated_at: '2026-05-10T10:00:00Z',
    primary_category: { id: 1, slug: 'city-guides', name: 'Guías de Ciudad' },
    author: { id: 1, name: 'Elena Rossi', role: 'Escritora de Viajes', bio: 'Biografía' },
    related_tours: [],
    related_posts: [],
  };

  const mockItPost = {
    id: 1,
    slug: 'florence-walk',
    title: 'Guida a Piedi di Firenze',
    excerpt: 'I migliori percorsi a Firenze.',
    content: '<p>Goditi Firenze.</p>',
    reading_time_minutes: 4,
    published_at: '2026-05-10T10:00:00Z',
    updated_at: '2026-05-10T10:00:00Z',
    primary_category: { id: 1, slug: 'city-guides', name: 'Guide Cittadine' },
    author: { id: 1, name: 'Elena Rossi', role: 'Scrittrice di Viaggi', bio: 'Biografia' },
    related_tours: [],
    related_posts: [],
  };

  test('renders Spanish locale content and canonical tags for blog post', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/florence-walk?locale=es', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockEsPost }),
      });
    });

    await page.goto('/es/blog/florence-walk');
    await expect(page.locator('h1')).toHaveText('Guía a Pie de Florencia');
    await expect(page.getByText('Escritora de Viajes')).toBeVisible();

    // Check canonical & hreflang
    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical).toContain('/es/blog/florence-walk');

    const hreflangEs = await page.locator('link[hreflang="es"]').getAttribute('href');
    expect(hreflangEs).toContain('/es/blog/florence-walk');
  });

  test('renders Italian locale content and canonical tags for blog post', async ({
    page,
  }) => {
    await page.route('**/api/public/blog/florence-walk?locale=it', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockItPost }),
      });
    });

    await page.goto('/it/blog/florence-walk');
    await expect(page.locator('h1')).toHaveText('Guida a Piedi di Firenze');
    await expect(page.getByText('Scrittrice di Viaggi')).toBeVisible();

    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical).toContain('/it/blog/florence-walk');
  });

  test('renders English locale content for blog post', async ({ page }) => {
    await page.route('**/api/public/blog/florence-walk?locale=en', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: mockEnPost }),
      });
    });

    await page.goto('/en/blog/florence-walk');
    await expect(page.locator('h1')).toHaveText('Florence Walking Guide');
  });
});
