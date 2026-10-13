import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { apiGet, ownedMedia, seededOwnedTourId } from '../partner/tour-content-helpers';

test.describe('Partner Dashboard Accessibility', () => {
  test('partner dashboard home should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page })
      .exclude('.recharts-surface') // Recharts SVG may have aria issues we can't control
      .analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner tours page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/tours');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner bookings page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/bookings');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner reviews page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/reviews');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });

  test('partner profile page should not have accessibility violations', async ({ page }) => {
    await page.goto('/en/partner/profile');
    await expect(page.locator('main#main-content')).toBeVisible();
    const results = await new AxeBuilder({ page }).analyze();
    expect(results.violations).toEqual([]);
  });
});

test.describe('Partner Layout Accessibility', () => {
  test('sidebar should have accessible navigation landmark', async ({ page }) => {
    await page.goto('/en/partner');

    // Sidebar should be a nav with aria-label
    const sidebar = page.locator('nav[aria-label]');
    await expect(sidebar).toBeVisible();

    // Destination navigation uses ordinary links, not application-menu roles.
    const navLinks = sidebar.getByRole('link');
    const linkCount = await navLinks.count();
    expect(linkCount).toBeGreaterThanOrEqual(5); // dashboard, tours, bookings, reviews, profile
  });

  test('sidebar links should have aria-current for active page', async ({ page }) => {
    await page.goto('/en/partner/tours');

    // The active tours link should have aria-current="page"
    const activeLink = page.locator('nav a[aria-current="page"]');
    await expect(activeLink).toBeVisible();
    await expect(activeLink).toHaveAttribute('href', /\/partner\/tours/);
  });

  test('mobile menu button should have aria-label', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    const menuButton = page.getByRole('button', { name: /^open navigation menu$/i });
    await expect(menuButton).toBeVisible();
  });

  test('mobile drawer should have proper ARIA attributes', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    // Open the drawer
    const menuButton = page.getByRole('button', { name: /^open navigation menu$/i });
    await menuButton.click();

    // Drawer should have dialog role and aria-modal
    const drawer = page.getByRole('dialog', { name: 'Main navigation', exact: true });
    await expect(drawer).toBeVisible();
    await expect(drawer).toHaveAttribute('aria-modal', 'true');

    // Close button should have aria-label
    const closeButton = drawer.getByRole('button', { name: 'Close menu', exact: true });
    await expect(closeButton).toBeVisible();
    await expect(closeButton).toBeFocused();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
    await closeButton.click();

    // Drawer should close
    await expect(drawer).not.toBeVisible();
    await expect(menuButton).toBeFocused();
  });

  test('notification bell should have accessible label', async ({ page }) => {
    await page.goto('/en/partner');

    const bellButton = page.getByRole('button', { name: /notifications/i });
    await expect(bellButton).toBeVisible();
  });

  test('header should have main content landmark', async ({ page }) => {
    await page.goto('/en/partner');

    // Main content area should have role="main"
    const main = page.locator('[role="main"]');
    await expect(main).toBeVisible();
  });

  test('form inputs should have associated labels', async ({ page }) => {
    await page.goto('/en/partner/profile');

    // All inputs should have associated labels (via htmlFor/id or aria-label)
    const inputs = page.locator('input[type="text"], input[type="email"], input[type="url"], input[type="tel"], textarea');
    const count = await inputs.count();

    for (let i = 0; i < count; i++) {
      const input = inputs.nth(i);
      const id = await input.getAttribute('id');
      if (id) {
        // Check that a label exists for this input
        const label = page.locator(`label[for="${id}"]`);
        await expect(label).toBeVisible();
      }
    }
  });

  test('switch toggles should have accessible roles', async ({ page }) => {
    await page.goto('/en/partner/profile');
    await expect(page.locator('main#main-content')).toBeVisible();

    // Scroll to notification settings
    const switches = page.locator('button[role="switch"]');
    await switches.first().waitFor();
    const switchCount = await switches.count();
    expect(switchCount).toBeGreaterThanOrEqual(5);

    // Each switch should have aria-checked
    for (let i = 0; i < switchCount; i++) {
      const sw = switches.nth(i);
      const ariaChecked = await sw.getAttribute('aria-checked');
      expect(ariaChecked).toBeTruthy();
    }
  });
});

// Spec019 T073 / FR-030 additions (author-only): affected TourWizard /
// ImageUploader / edit-page facts only. No route mocks, no backend writes, no
// Axe exclusions. Widths/locales are distributed (not full cartesian) to avoid
// duplicated exhaustive loops; full coverage is left for ROOT manual.
test.describe('Partner Tour Wizard Accessibility (Spec019 FR-030)', () => {
  test('wizard create page has no axe violations across distributed locales and widths', async ({
    page,
  }) => {
    const cases = [
      { locale: 'en', width: 390 },
      { locale: 'es', width: 768 },
      { locale: 'it', width: 1024 },
      { locale: 'en', width: 1440 },
    ] as const;
    for (const { locale, width } of cases) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/${locale}/partner/tours/create`);
      await expect(page.locator('main#main-content')).toBeVisible();
      const results = await new AxeBuilder({ page }).analyze();
      expect(results.violations).toEqual([]);
    }
  });

  test('wizard create layout width has no axe violations', async ({ page }) => {
    // 320 is layout-relevant for the wizard grids and uploader thumbnails.
    await page.setViewportSize({ width: 320, height: 900 });
    await page.goto('/en/partner/tours/create');
    await expect(page.locator('main#main-content')).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });

  test('difficulty and guide-language controls are labeled in each page language', async ({ page }) => {
    const expectations = [
      { locale: 'en', difficulty: 'Difficulty Level', guide: 'Live-guide language codes' },
      { locale: 'es', difficulty: 'Nivel de Dificultad', guide: 'en vivo' },
      { locale: 'it', difficulty: 'Livello di Difficolt', guide: 'guida dal vivo' },
    ] as const;
    for (const { locale, difficulty, guide } of expectations) {
      await page.goto(`/${locale}/partner/tours/create`);
      await expect(page.locator('main#main-content')).toBeVisible();
      // Difficulty select keeps a stable accessible name per page language and
      // starts blank: unknown difficulty is never invented as easy.
      const difficultyTrigger = page.locator(
        'button[aria-labelledby="difficulty_level-label"], [aria-labelledby="difficulty_level-label"]',
      ).first();
      await expect(page.locator('#difficulty_level-label', { hasText: difficulty }).first()).toBeVisible();
      await expect(difficultyTrigger).toBeVisible();
      // Guide codes: labeled input, hint association, page-translation scope.
      const guideInput = page.locator('#guide_languages');
      await expect(page.locator('label[for="guide_languages"]', { hasText: guide }).first()).toBeVisible();
      await expect(guideInput).toHaveAttribute('aria-describedby', 'guide-languages-hint');
      await expect(page.locator('#guide-languages-hint')).toBeVisible();
    }
  });

  test('english-source notice stays visible and page-localized on details step', async ({ page }) => {
    await page.goto('/es/partner/tours/create');
    await expect(page.locator('main#main-content')).toBeVisible();
    // Spanish page notice: authors edit English source; derivatives never block.
    await expect(page.getByText(/traducci/i).first()).toBeVisible();
    await page.goto('/it/partner/tours/create');
    await expect(page.getByText(/inglese/i).first()).toBeVisible();
  });

  test('empty wizard next announces validation errors and moves focus to the summary', async ({
    page,
  }) => {
    // Client-side validation only: no backend write, no route mock.
    await page.goto('/en/partner/tours/create');
    await expect(page.locator('main#main-content')).toBeVisible();
    await page.getByRole('button', { name: /Next/i }).click();
    const summary = page.getByRole('alert').first();
    await expect(summary).toBeVisible();
    expect(await summary.getAttribute('aria-live')).toBe('assertive');
    await expect(summary).toBeFocused();
    // Field-level announced error for the missing English title.
    await expect(page.locator('p[role="alert"]', { hasText: /.+/ }).first()).toBeVisible();
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });

  test('uploader dropzone and alt-field contract on the edit page', async ({ page }) => {
    // Existing tour edit surface; reads only, no writes, no mocks.
    await page.goto('/en/partner/tours');
    const tourId = await seededOwnedTourId(page);
    const stored = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(stored.status).toBe(200);
    const storedMedia = ownedMedia(stored.json);
    expect(storedMedia.length).toBeGreaterThan(0);
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('main#main-content')).toBeVisible();
    // Dropzone file input and image-count status stay exposed to AT.
    await expect(page.locator('input[type="file"][accept*="image"]')).toHaveCount(1);
    await expect(page.getByText(/images?/i).first()).toBeVisible();

    const altInputs = page.locator('input[id^="tour-image-alt-"]');
    await expect(altInputs).toHaveCount(storedMedia.length);
    const altCount = await altInputs.count();
    for (let i = 0; i < altCount; i++) {
      const input = altInputs.nth(i);
      const id = (await input.getAttribute('id')) ?? '';
      await expect(input).toHaveAttribute('lang', 'en');
      // Stable label/id with optional-English meaning; never a page-translation field.
      await expect(page.locator(`label[for="${id}"]`, { hasText: /English/i })).toBeVisible();
      const describedBy = await input.getAttribute('aria-describedby');
      if (describedBy) {
        const alert = page.locator(`#${describedBy}`);
        await expect(alert).toHaveAttribute('role', 'alert');
      }
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
  });
});
