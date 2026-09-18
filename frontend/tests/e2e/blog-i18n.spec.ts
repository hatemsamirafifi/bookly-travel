import { test, expect } from '@playwright/test';

// These tests exercise the REAL backend seeded by BlogBrowserTestSeeder.
// The multilingual florence-walk fixture provides EN/ES/IT content, so the
// locale rendering and canonical/hreflang assertions run against genuine
// server-rendered data. Browser page.route() mocks were removed because
// they cannot intercept Next.js Server Component fetches.

test.describe('Blog Multi-Language i18n Verification', () => {
  test('renders Spanish locale content and canonical tags for blog post', async ({
    page,
  }) => {
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
    await page.goto('/it/blog/florence-walk');
    await expect(page.locator('h1')).toHaveText('Guida a Piedi di Firenze');
    await expect(page.getByText('Scrittrice di Viaggi')).toBeVisible();

    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical).toContain('/it/blog/florence-walk');
  });

  test('renders English locale content for blog post', async ({ page }) => {
    await page.goto('/en/blog/florence-walk');
    await expect(page.locator('h1')).toHaveText('Florence Walking Guide');
  });
});