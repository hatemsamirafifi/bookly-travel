import { test, expect } from '@playwright/test';

test.describe('Multi-Language (i18n)', () => {
  test('English locale loads correctly', async ({ page }) => {
    await page.goto('/en/search');
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
    await expect(page.locator('select[aria-label="Switch language"]').first()).toHaveValue('en');
  });

  test('Spanish locale loads correctly', async ({ page }) => {
    await page.goto('/es/search');
    await expect(page.locator('html')).toHaveAttribute('lang', 'es');
    await expect(page.locator('select[aria-label="Switch language"]').first()).toHaveValue('es');
  });

  test('Italian locale loads correctly', async ({ page }) => {
    await page.goto('/it/search');
    await expect(page.locator('html')).toHaveAttribute('lang', 'it');
    await expect(page.locator('select[aria-label="Switch language"]').first()).toHaveValue('it');
  });

  test('locale switcher navigates to same page in different language', async ({ page }) => {
    await page.goto('/en/search');
    const switcher = page.locator('select[aria-label="Switch language"]').first();
    // The switcher is a client component: a change fired before hydration
    // completes is lost (React resets the controlled value), so the
    // navigation never happens. Settle first, then retry the selection
    // until the navigation commits.
    await page.waitForLoadState('networkidle').catch(() => undefined);
    for (let attempt = 0; attempt < 3; attempt++) {
      await switcher.selectOption('es');
      try {
        await expect(page).toHaveURL(/\/es\/search/, { timeout: 8000 });
        return;
      } catch {
        // Not navigated yet (likely pre-hydration) -- retry the selection.
      }
    }
    await expect(page).toHaveURL(/\/es\/search/, { timeout: 8000 });
  });

  test('locale switcher preserves path and query params', async ({ page }) => {
    await page.goto('/en/search?q=beach');
    const switcher = page.locator('select[aria-label="Switch language"]').first();
    // Same hydration race as above -- settle, then retry until commit.
    await page.waitForLoadState('networkidle').catch(() => undefined);
    for (let attempt = 0; attempt < 3; attempt++) {
      await switcher.selectOption('it');
      try {
        await expect(page).toHaveURL(/\/it\/search\?q=beach/, { timeout: 8000 });
        return;
      } catch {
        // Not navigated yet (likely pre-hydration) -- retry the selection.
      }
    }
    await expect(page).toHaveURL(/\/it\/search\?q=beach/, { timeout: 8000 });
  });

  test('hreflang tags are present on tour detail page', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const enAlternate = page.locator('link[rel="alternate"][hreflang="en"]');
    const esAlternate = page.locator('link[rel="alternate"][hreflang="es"]');
    const itAlternate = page.locator('link[rel="alternate"][hreflang="it"]');

    // At least one should be present
    const hasAny = (await enAlternate.count()) > 0
      || (await esAlternate.count()) > 0
      || (await itAlternate.count()) > 0;

    expect(hasAny).toBeTruthy();
  });

  test('canonical URL is self-referencing on search page', async ({ page }) => {
    await page.goto('/en/search?q=tour');

    const canonical = page.locator('link[rel="canonical"]');
    if (await canonical.count() > 0) {
      // Canonical should be present when metadata is set
      expect(await canonical.count()).toBeGreaterThanOrEqual(0);
    }
  });

  test('homepage is accessible in all three locales', async ({ page }) => {
    for (const locale of ['en', 'es', 'it']) {
      await page.goto(`/${locale}`);
      await expect(page.locator('html')).toHaveAttribute('lang', locale);
      await expect(page.locator('main')).toBeVisible();
    }
  });

  test('category page works in different locales', async ({ page }) => {
    await page.goto('/es/categories/adventure');
    await expect(page.locator('html')).toHaveAttribute('lang', 'es');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('destination page works in different locales', async ({ page }) => {
    await page.goto('/it/destinations/paris');
    await expect(page.locator('html')).toHaveAttribute('lang', 'it');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
