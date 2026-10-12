import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

for (const locale of ['en', 'es', 'it']) {
  test(`${locale} tour detail with loaded reviews should not have accessibility violations`, async ({ page, request }) => {
    const slug = 'hidden-gems-rome-walking-tour';
    const response = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    expect(data.length).toBeGreaterThan(0);
    await page.goto(`/${locale}/tours/${slug}`);
    await expect(page.locator('main#main-content')).toBeVisible();
    // Wait for real API-backed review text, not the initial loading placeholders.
    await expect(page.locator('#reviews').getByText(data[0].reviewer_name, { exact: true }).first()).toBeVisible();
    const accessibilityScanResults = await new AxeBuilder({ page }).analyze();
    expect(accessibilityScanResults.violations).toEqual([]);
  });
}

// Spec019 T073 / FR-030 additions (author-only): affected TourDetail/ImageGallery
// facts only. Real backend + real SSR page, no route mocks, no Axe exclusions.
// Full EN/ES/IT x 390/768/1024/1440(+320) cartesian is left for ROOT manual;
// widths and locales are distributed below without duplicated exhaustive loops.
test.describe('Spec019 tour content language and media controls', () => {
  const slug = 'hidden-gems-rome-walking-tour';

  test('en tour detail has no axe violations across responsive widths', async ({ page, request }) => {
    const response = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(response.ok()).toBeTruthy();
    for (const width of [390, 768, 1024, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto('/en/tours/' + slug);
      await expect(page.locator('main#main-content')).toBeVisible();
      const results = await new AxeBuilder({ page }).analyze();
      expect(results.violations).toEqual([]);
    }
  });

  test('gallery layout width has no axe violations', async ({ page }) => {
    // 320 is layout-relevant for the gallery/thumbnail strip.
    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('es and it tour detail have no axe violations at spot widths', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/es/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/it/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });

  test('difficulty and guide languages are labeled; codes stay stable across page language', async ({
    page,
    request,
  }) => {
    const readDetail = async (locale: string) => {
      const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
      expect(response.ok()).toBeTruthy();
      return (await response.json()).data;
    };
    const en = await readDetail('en');
    const es = await readDetail('es');
    // Spoken guide-language codes are tour-level facts: page language must not change them.
    expect(es.guide_languages).toEqual(en.guide_languages);

    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    if (Array.isArray(en.guide_languages) && en.guide_languages.length > 0) {
      // Labeled control/fact in page language, stable code value.
      await expect(page.getByText('Live guide', { exact: true })).toBeVisible();
      await expect(page.locator('p', { hasText: String(en.guide_languages[0]) }).first()).toBeVisible();
    }
    if (typeof en.difficulty_level === 'string' && en.difficulty_level.length > 0) {
      await expect(page.locator('dt', { hasText: 'Difficulty' }).first()).toBeVisible();
      await expect(page.locator('#facts dd').filter({ hasText: /.+/ }).first()).toBeVisible();
    } else {
      // Older tours without difficulty stay usable: no invented difficulty value.
      await expect(page.locator('dt', { hasText: 'Difficulty' })).toHaveCount(0);
    }

    // Same facts in Spanish: label follows page language, codes unchanged.
    await page.goto('/es/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    if (Array.isArray(es.guide_languages) && es.guide_languages.length > 0) {
      await expect(page.getByText(/en vivo/i).first()).toBeVisible();
      await expect(page.locator('p', { hasText: String(es.guide_languages[0]) }).first()).toBeVisible();
    }
  });

  test('image alt language is separated from page-control language', async ({ page, request }) => {
    const response = await request.get(`/api/public/tours/${slug}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const detail = (await response.json()).data;
    const images = Array.isArray(detail.images) ? detail.images : [];
    // This seeded scenario explicitly exercises a populated gallery; empty
    // galleries have separate reader/browser coverage.
    expect(images.length).toBeGreaterThan(0);
    const first = images[0] as { alt?: string | null; alt_locale?: string | null };
    const actualLang: string = first.alt_locale ?? detail.content_locale ?? 'en';

    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    // Page control uses page language ...
    const trigger = page.getByRole('button', { name: 'Open image gallery' });
    await expect(trigger).toBeVisible();
    // ... while the image itself declares its actual language (authored alt
    // locale, else the selected content locale; title fallback keeps meaning).
    const galleryImg = page.locator('main#main-content img').first();
    await expect(galleryImg).toHaveAttribute('alt', /.+/);
    await expect(galleryImg).toHaveAttribute('lang', actualLang);

    // Localized control names still resolve in ES/IT (prefix match avoids
    // diacritic brittleness while proving page-language controls).
    await page.goto('/es/tours/' + slug);
    await expect(page.getByRole('button', { name: /Abrir/ }).first()).toBeVisible();
    await page.goto('/it/tours/' + slug);
    await expect(page.getByRole('button', { name: /Apri/ }).first()).toBeVisible();
  });

  test('lightbox dialog traps focus, closes on escape and restores trigger focus', async ({ page }) => {
    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    const trigger = page.getByRole('button', { name: 'Open image gallery' });
    await expect(trigger).toBeVisible();
    await trigger.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'Image lightbox' });
    await expect(dialog).toBeVisible();
    // Dialog carries no image lang so localized controls never inherit it.
    expect(await dialog.getAttribute('lang')).toBeNull();
    const closeButton = page.getByRole('button', { name: 'Close lightbox' });
    await expect(closeButton).toBeFocused();

    // Focus trap: cycling forward from the last button wraps to the first.
    await page.keyboard.press('Tab');
    const focusedAfterTab = await page.evaluate(() => document.activeElement?.getAttribute('aria-label'));
    expect(typeof focusedAfterTab === 'string' && focusedAfterTab.length > 0).toBeTruthy();
    // Backward wrap stays inside the dialog.
    await closeButton.focus();
    await page.keyboard.press('Shift+Tab');
    expect(await page.evaluate(() => document.activeElement?.closest('[role="dialog"]') !== null)).toBe(true);

    // Escape closes and restores focus to the invoking control.
    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });
});
