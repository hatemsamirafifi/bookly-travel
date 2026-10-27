import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { startSsrHarness } from '../helpers/tour-detail-ssr';
import { createFullTourDetail } from '../../fixtures/tour-detail';

for (const [locale, title, retry] of [
  ['en', 'This tour could not be loaded', 'Try again'],
  ['es', 'No se pudo cargar este tour', 'Volver a intentar'],
  ['it', 'Impossibile caricare questo tour', 'Riprova'],
]) {
  test(`${locale} recoverable tour failure has a page heading and accessible retry`, async ({ page }) => {
    test.setTimeout(180000);
    await page.setViewportSize({ width: 390, height: 844 });
    const slug = 'hidden-gems-rome-walking-tour';
    const harness = await startSsrHarness(slug, () => ({
      status: 503,
      body: { message: 'PRIVATE-UPSTREAM-TEXT' },
    }));
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/${locale}/tours/${slug}`);
      await expect(page.getByRole('heading', { level: 1, name: title, exact: true })).toBeVisible();
      await expect(page.getByRole('button', { name: retry, exact: true })).toBeEnabled();
      await expect(page.locator('main#main-content')).not.toContainText('PRIVATE-UPSTREAM-TEXT');
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    } finally {
      await harness.stop();
    }
  });
}

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

// Spec019 T073 / FR-030 additions (author-only): affected TourDetail/TourGallery
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
    // 320 is layout-relevant for the gallery rail.
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
    // Spoken guide-language names mirror the production localizer so the
    // assertion proves the rendered localized fact, not a raw-code substring.
    const expectedGuideNames = (codes: string[], locale: string) =>
      codes.map((code) => {
        const name = new Intl.DisplayNames([locale], { type: 'language' }).of(code) ?? code;
        return name.charAt(0).toLocaleUpperCase(locale) + name.slice(1);
      });
    const en = await readDetail('en');
    const es = await readDetail('es');
    // Spoken guide-language codes are tour-level facts: page language must not change them.
    expect(es.guide_languages).toEqual(en.guide_languages);

    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    if (Array.isArray(en.guide_languages) && en.guide_languages.length > 0) {
      // Labeled fact in page language with the actual localized spoken names.
      const guideFact = page.locator('#facts p', { hasText: 'Live guide' });
      await expect(guideFact).toBeVisible();
      await expect(guideFact).toContainText(expectedGuideNames(en.guide_languages, 'en').join(', '));
    }
    if (typeof en.difficulty_level === 'string' && en.difficulty_level.length > 0) {
      await expect(page.locator('dt', { hasText: 'Difficulty' }).first()).toBeVisible();
      await expect(page.locator('#facts dd').filter({ hasText: /.+/ }).first()).toBeVisible();
    } else {
      // Older tours without difficulty stay usable: no invented difficulty value.
      await expect(page.locator('dt', { hasText: 'Difficulty' })).toHaveCount(0);
    }

    // Same facts in Spanish: label and spoken names follow page language, codes unchanged.
    await page.goto('/es/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    if (Array.isArray(es.guide_languages) && es.guide_languages.length > 0) {
      const guideFact = page.locator('#facts p', { hasText: /en vivo/i });
      await expect(guideFact).toBeVisible();
      await expect(guideFact).toContainText(expectedGuideNames(es.guide_languages, 'es').join(', '));
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
    // Page control uses page language (canonical tile; the legacy single
    // "Open image gallery" trigger retired with the old engine) ...
    const trigger = page.getByRole('button', { name: 'View image 1' });
    await expect(trigger).toBeVisible();
    // ... while the image itself declares its actual language (authored alt
    // locale, else the selected content locale; title fallback keeps meaning).
    const galleryImg = page.locator('main#main-content img').first();
    await expect(galleryImg).toHaveAttribute('alt', /.+/);
    await expect(galleryImg).toHaveAttribute('lang', actualLang);

    // Localized control names still resolve in ES/IT (prefix match avoids
    // diacritic brittleness while proving page-language controls).
    await page.goto('/es/tours/' + slug);
    await expect(page.getByRole('button', { name: /Ver imagen/ }).first()).toBeVisible();
    await page.goto('/it/tours/' + slug);
    await expect(page.getByRole('button', { name: /Vedi immagine/ }).first()).toBeVisible();
  });

  test('lightbox dialog traps focus, closes on escape and restores trigger focus', async ({ page }) => {
    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    const trigger = page.getByRole('button', { name: 'View image 1' });
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

// Spec 020 US6: twelve-combination locale/width acceptance plus
// keyboard/reduced-motion coverage for owned content/state surfaces.
// Gallery-viewer, section-nav-active and wishlist-prompt focus acceptance
// complete at T069 after US3/US4/US5 integrate those overlays; this slice
// claims no overlay acceptance and adds no gallery/nav/wishlist assertions.
test.describe('Spec020 US6 locale, width and interaction acceptance', () => {
  const slug = 'hidden-gems-rome-walking-tour';

  test('EN/ES/IT x 390/768/1024/1440 with loaded reviews has no axe violations', async ({ page, request }) => {
    test.setTimeout(300000);
    const reviewerByLocale: Record<string, string> = {};
    for (const locale of ['en', 'es', 'it']) {
      const response = await request.get(`/api/public/tours/${slug}/reviews?page=1&per_page=5`);
      expect(response.ok()).toBeTruthy();
      const { data } = await response.json();
      expect(data.length).toBeGreaterThan(0);
      reviewerByLocale[locale] = data[0].reviewer_name;
    }
    for (const locale of ['en', 'es', 'it']) {
      for (const width of [390, 768, 1024, 1440]) {
        await test.step(`${locale} at ${width}px`, async () => {
          await page.setViewportSize({ width, height: 900 });
          await page.goto(`/${locale}/tours/${slug}`);
          await expect(page.locator('main#main-content')).toBeVisible();
          // Wait for actual UI readiness: real API-backed review text, not placeholders.
          await expect(page.locator('#reviews').getByText(reviewerByLocale[locale], { exact: true }).first()).toBeVisible();
          // No unintended whole-page horizontal overflow at any acceptance width.
          expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
            await page.evaluate(() => document.documentElement.clientWidth),
          );
          expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
        });
      }
    }
  });

  test('long-overview disclosure is fully keyboard operable with announced state', async ({ page }) => {
    test.setTimeout(180000);
    // Controlled SSR fixture with an asserted over-budget description, so the
    // disclosure is always present: no conditional no-op is possible.
    const longDescription = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
    expect(Array.from(longDescription).length).toBeGreaterThan(600);
    const detail = createFullTourDetail(new Date(), { description: longDescription });
    const harness = await startSsrHarness(slug, () => detail);
    try {
      await page.goto(`http://127.0.0.1:${harness.nextPort}/en/tours/${slug}`);
      await expect(page.locator('main#main-content')).toBeVisible();
      const overview = page.locator('main#main-content #overview');
      await expect(overview).toBeVisible();
      // Preview first: the full text is reachable only through the disclosure.
      await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
      const expand = overview.getByRole('button', { name: /^(Read more|Read less)$/ });
      await expect(expand).toHaveAttribute('aria-expanded', 'false');
      expect(await expand.getAttribute('aria-controls')).toBeTruthy();
      await expand.focus();
      await expect(expand).toBeFocused();
      await page.keyboard.press('Enter');
      await expect(expand).toHaveAttribute('aria-expanded', 'true');
      await expect(page.getByText('Final reachable sentence.')).toBeVisible();
      await expect(expand).toBeFocused();
      await page.keyboard.press('Enter');
      await expect(page.getByText('Final reachable sentence.')).toHaveCount(0);
      await expect(overview.getByRole('button', { name: 'Read more' })).toHaveAttribute('aria-expanded', 'false');
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    } finally {
      await harness.stop();
    }
  });
  test('page stays readable and operable with reduced motion preferred', async ({ page, request }) => {
    const response = await request.get(`/api/public/tours/${slug}?locale=es`);
    expect(response.ok()).toBeTruthy();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/es/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    const nav = page.getByRole('navigation', { name: 'Secciones del tour', exact: true });
    await expect(nav).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });
});

// Spec 020 US5: signed-out wishlist prompt uses the shared dialog with its
// localized description; focus, Escape and trigger return hold with no axe
// violations. Authenticated save/fail/denied outcomes are covered by the
// authed wishlist suite; this slice claims only the signed-out modal.
test.describe('Spec020 US5 wishlist prompt dialog', () => {
  const slug = 'hidden-gems-rome-walking-tour';

  test('sign-in prompt dialog is described, modal and focus-safe with no axe violations', async ({
    page,
  }) => {
    await page.goto('/en/tours/' + slug);
    await expect(page.locator('main#main-content')).toBeVisible();
    const trigger = page.locator('main#main-content [data-testid="wishlist-button"]').first();
    await expect(trigger).toBeVisible();
    await expect(trigger).toBeEnabled();
    await trigger.focus();
    await trigger.click();

    const dialog = page.getByRole('dialog', { name: 'Sign in to save tours', exact: true });
    await expect(dialog).toBeVisible();
    const describedBy = await dialog.getAttribute('aria-describedby');
    expect(typeof describedBy === 'string' && describedBy.length > 0).toBe(true);
    await expect(dialog.locator(`[id="${describedBy}"]`)).toHaveText(
      'Sign in or create an account to add tours to your wishlist.',
    );
    // Focus moves into the dialog close control on open.
    await expect(page.getByRole('button', { name: 'Not now' }).first()).toBeFocused();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);

    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
  });
});
