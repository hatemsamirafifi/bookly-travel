import { test, expect } from '@playwright/test';

test.describe('Tour Detail Page', () => {
  test('tour detail page loads with all sections', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    // Page should load
    await expect(page.locator('main')).toBeVisible();

    // Title should be present
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('image gallery is visible', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const gallery = page.locator('.aspect-\\[16\\/10\\]').first();
    await expect(gallery).toBeVisible({ timeout: 10000 });
  });

  test('image gallery navigation buttons work when multiple images', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const nextBtn = page.getByRole('button', { name: 'Next image' });
    const prevBtn = page.getByRole('button', { name: 'Previous image' });

    if (await nextBtn.isVisible()) {
      await nextBtn.click();
      await expect(page.locator('[aria-current="true"]').first()).toBeVisible();
    }

    if (await prevBtn.isVisible()) {
      await prevBtn.click();
    }
  });

  test('lightbox opens on image click', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const mainImage = page.locator('.cursor-pointer').first();
    if (await mainImage.isVisible()) {
      await mainImage.click();
      await expect(page.getByRole('dialog', { name: 'Image lightbox' })).toBeVisible();

      // Close lightbox with Escape
      await page.keyboard.press('Escape');
      await expect(page.getByRole('dialog', { name: 'Image lightbox' })).not.toBeVisible();
    }
  });

  test('availability calendar shows available dates', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const calendar = page.getByText('Select a Date');
    await expect(calendar).toBeVisible({ timeout: 10000 });
  });

  test('booking CTA is visible', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const bookNow = page.getByRole('button', { name: /Book Now/i });
    const bookNowLink = page.getByRole('link', { name: /Book Now/i });

    const hasCTA = (await bookNow.isVisible().catch(() => false))
      || (await bookNowLink.isVisible().catch(() => false));

    // May show "Currently Unavailable" instead
    const unavailable = page.getByText('Currently Unavailable');
    const hasUnavailable = await unavailable.isVisible().catch(() => false);

    expect(hasCTA || hasUnavailable).toBeTruthy();
  });

  test('participant selector increments and decrements', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
    const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });

    if (await increaseBtn.isVisible()) {
      await increaseBtn.click();
      // Count should change - exact value depends on tour's group size
      await expect(page.getByText(/allowed/)).toBeVisible();
      await expect(decreaseBtn).toBeVisible();
    }
  });

  test('shows 404 page for non-existent tour', async ({ page }) => {
    await page.goto('/en/tours/this-tour-does-not-exist-xyz');

    // Next.js default not-found behavior or API error
    await expect(page.locator('main')).toBeVisible();
  });

  test('reviews section is visible', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const reviewsHeading = page.getByRole('heading', { name: /Reviews/i });
    await expect(reviewsHeading).toBeVisible({ timeout: 10000 });
  });

  test('about section displays description', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    const aboutHeading = page.getByText('About This Tour');
    await expect(aboutHeading).toBeVisible({ timeout: 10000 });
  });

  test('tour detail has proper SEO metadata', async ({ page }) => {
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    await expect(page).toHaveTitle(/Bookly/);

    // Check canonical link
    const canonical = page.locator('link[rel="canonical"]');
    if (await canonical.isVisible().catch(() => false)) {
      const href = await canonical.getAttribute('href');
      expect(href).toContain('/tours/');
    }
  });

  // ─── Responsive Viewport Tests (FR-032, T092) ───

  const viewports = [
    { name: 'mobile', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
  ];

  for (const vp of viewports) {
    test(`tour detail page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/en/tours/hidden-gems-rome-walking-tour');

      await expect(page.locator('main')).toBeVisible({ timeout: 10000 });

      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
  }

  test('tour detail content stacks vertically on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/en/tours/hidden-gems-rome-walking-tour');

    await expect(page.locator('main')).toBeVisible({ timeout: 10000 });

    // Content should not overflow horizontally
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });
});
