import { test, expect } from '@playwright/test';

test.describe('Homepage', () => {
  test.beforeEach(async ({ context }) => {
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);
  });

  test('hero renders with search fields', async ({ page }) => {
    await page.goto('/en/');
    await page.goto('/en');
    await expect(page.getByRole('heading', { name: /Discover & Book Amazing Tours/i })).toBeVisible();
    await expect(page.getByPlaceholder(/Search tours/i)).toBeVisible();
  });

  test('categories are visible', async ({ page }) => {
    await page.goto('/en/');
    await page.goto('/en');
    await expect(page.getByRole('heading', { name: /Popular Categories/i })).toBeVisible();
    const cards = page.locator('section:has-text("Popular Categories") a');
    await expect(cards.first()).toBeVisible();
  });

  test('featured tours are visible', async ({ page }) => {
    await page.goto('/en/');
    await page.goto('/en');
    await expect(page.getByRole('heading', { name: /Featured Tours/i })).toBeVisible();
  });

  test('locale switching works', async ({ page }) => {
    await page.goto('/en/');
    await page.selectOption('select[aria-label="Switch language"]', 'es');
    await page.goto('/en');
    await page.locator('select[aria-label="Switch language"]').first().selectOption('es');
    await expect(page).toHaveURL(/\/es/);
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/');
    await page.goto('/en');
    await expect(page.getByRole('heading', { name: /Discover & Book Amazing Tours/i })).toBeVisible();
  });
});
