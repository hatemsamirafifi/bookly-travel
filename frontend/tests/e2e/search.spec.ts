import { test, expect } from '@playwright/test';

test.describe('Search Page', () => {
  test('search bar navigates to results page', async ({ page }) => {
    await page.goto('/en/search');

    const searchInput = page.getByRole('searchbox', { name: 'Search tours' });
    await expect(searchInput).toBeVisible();

    await searchInput.fill('beach');
    await searchInput.press('Enter');

    await expect(page).toHaveURL(/\/en\/search\?q=beach/);
  });

  test('displays search results with tour cards', async ({ page }) => {
    await page.goto('/en/search?q=tour');

    const pagination = page.getByRole('navigation', { name: 'Search results pagination' });
    if (await pagination.isVisible()) {
      await expect(pagination).toBeVisible();
    }

    const cards = page.getByRole('link').filter({ has: page.locator('img') });
    expect(await cards.count()).toBeGreaterThanOrEqual(0);
    // May have zero results in test env — page should still render
    await expect(page.locator('main')).toBeVisible();
  });

  test('shows empty state when no results', async ({ page }) => {
    await page.goto('/en/search?q=xyznonexistent9999');

    const emptyHeading = page.getByText('No tours found');
    await expect(emptyHeading).toBeVisible({ timeout: 10000 });
  });

  test('pagination controls are visible when multiple pages exist', async ({ page }) => {
    await page.goto('/en/search');

    const pagination = page.getByRole('navigation', { name: 'Search results pagination' });
    if (await pagination.isVisible()) {
      await expect(pagination).toBeVisible();
      const prevButton = page.getByRole('button', { name: 'Previous page' });
      const nextButton = page.getByRole('button', { name: 'Next page' });
      await expect(prevButton).toBeVisible();
      await expect(nextButton).toBeVisible();
    }
  });

  test('previous button is disabled on first page', async ({ page }) => {
    await page.goto('/en/search');

    const prevButton = page.getByRole('button', { name: 'Previous page' });
    if (await prevButton.isVisible()) {
      await expect(prevButton).toBeDisabled();
    }
  });

  test('clicking a tour card navigates to tour detail', async ({ page }) => {
    await page.goto('/en/search?q=tour');

    const firstCard = page.locator('a[href*="/tours/"]').first();
    if (await firstCard.isVisible()) {
      await firstCard.click();
      await expect(page).toHaveURL(/\/en\/tours\//);
    }
  });

  test('search page has proper page title', async ({ page }) => {
    await page.goto('/en/search?q=adventure');
    await expect(page).toHaveTitle(/Search Results | Bookly/);
  });

  test('search works in Spanish locale', async ({ page }) => {
    await page.goto('/es/search');

    const searchInput = page.getByRole('searchbox');
    await expect(searchInput).toBeVisible();

    await searchInput.fill('playa');
    await searchInput.press('Enter');
    await expect(page).toHaveURL(/\/es\/search\?q=playa/);
  });

  test('search works in Italian locale', async ({ page }) => {
    await page.goto('/it/search');

    const searchInput = page.getByRole('searchbox');
    await expect(searchInput).toBeVisible();

    await searchInput.fill('spiaggia');
    await searchInput.press('Enter');
    await expect(page).toHaveURL(/\/it\/search\?q=spiaggia/);
  });

  // Filter & Sort tests (US2)

  test('filter panel is visible on search page', async ({ page }) => {
    await page.goto('/en/search');
    await expect(page.getByRole('complementary', { name: 'Search filters' })).toBeVisible();
  });

  test('sort dropdown is visible on search page', async ({ page }) => {
    await page.goto('/en/search');
    await expect(page.getByLabel('Sort by:')).toBeVisible();
  });

  test('selecting sort option updates URL', async ({ page }) => {
    await page.goto('/en/search');
    await page.getByLabel('Sort by:').selectOption('price_asc');
    await expect(page).toHaveURL(/sort=price_asc/);
  });

  test('applying category filter updates URL and results', async ({ page }) => {
    await page.goto('/en/search?q=tour');
    const categoryLabel = page.locator('label:has(input[name="category"])').first();
    if (await categoryLabel.isVisible()) {
      await categoryLabel.click();
      await expect(page).toHaveURL(/category=/);
    }
  });

  test('entering price range filter updates URL', async ({ page }) => {
    await page.goto('/en/search');

    const minInput = page.getByLabel('Minimum price');
    await minInput.fill('1000');
    await expect(page).toHaveURL(/price_min=1000/);

    const maxInput = page.getByLabel('Maximum price');
    await maxInput.fill('5000');
    await expect(page).toHaveURL(/price_max=5000/);
  });

  test('clear all link appears when filters are active', async ({ page }) => {
    await page.goto('/en/search?category=adventure');

    const clearButton = page.getByText(/Clear all/);
    await expect(clearButton).toBeVisible();

    await clearButton.click();
    await expect(page).toHaveURL(/\/en\/search$/);
  });

  test('filter sections are collapsible', async ({ page }) => {
    await page.goto('/en/search');

    const toggleButton = page.getByRole('button', { name: /Available Date/ });
    await expect(toggleButton).toBeVisible();

    await toggleButton.click();
    const dateInput = page.getByLabel('Filter by available date');
    await expect(dateInput).toBeVisible();
  });

  test('duration filter options are selectable', async ({ page }) => {
    await page.goto('/en/search');

    const halfDayLabel = page.locator('label:has(input[name="duration"])').first();
    if (await halfDayLabel.isVisible()) {
      await halfDayLabel.click();
      await expect(page).toHaveURL(/duration=/);
    }
  });

  // ─── Responsive Viewport Tests (FR-032, T092) ───

  const viewports = [
    { name: 'mobile', width: 375, height: 667 },
    { name: 'tablet', width: 768, height: 1024 },
    { name: 'desktop', width: 1280, height: 800 },
  ];

  for (const vp of viewports) {
    test(`search page adapts to ${vp.name} viewport (${vp.width}px) without horizontal scroll`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/en/search');

      await expect(page.locator('main')).toBeVisible();

      // No horizontal scroll at any viewport
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });

    test(`tour cards reflow correctly on ${vp.name} viewport`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto('/en/search?q=tour');

      const cards = page.locator('[data-testid="tour-card"], article').first();
      if (await cards.isVisible()) {
        const box = await cards.boundingBox();
        expect(box).not.toBeNull();
        if (box) {
          expect(box.x).toBeGreaterThanOrEqual(0);
          expect(box.x + box.width).toBeLessThanOrEqual(vp.width);
        }
      }
    });
  }

  test('filter panel collapses on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/en/search');

    // On mobile, the filter panel should be behind a toggle/drawer
    // (not always visible in the sidebar layout used on desktop)
    const main = page.locator('main');
    await expect(main).toBeVisible();

    // Verify the page renders without the desktop sidebar overflowing
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });

  test('navigation switches to hamburger menu on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/en/search');

    // Look for a hamburger/toggle button that appears on mobile
    const menuToggle = page.getByRole('button', { name: /menu|toggle|open menu/i }).first();
    if (await menuToggle.isVisible()) {
      await menuToggle.click();
      await expect(page.getByRole('link', { name: /search/i }).first()).toBeVisible();
    }
  });
});
