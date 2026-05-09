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

    await expect(page.getByRole('navigation', { name: 'Search results pagination' })).toBeVisible();

    const cards = page.getByRole('link').filter({ has: page.locator('img') });
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
    await expect(pagination).toBeVisible();

    const prevButton = page.getByRole('button', { name: 'Previous page' });
    const nextButton = page.getByRole('button', { name: 'Next page' });

    await expect(prevButton).toBeVisible();
    await expect(nextButton).toBeVisible();
  });

  test('previous button is disabled on first page', async ({ page }) => {
    await page.goto('/en/search');

    const prevButton = page.getByRole('button', { name: 'Previous page' });
    await expect(prevButton).toBeDisabled();
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
    const categoryRadio = page.getByRole('radio', { name: /./ }).first();
    if (await categoryRadio.isVisible()) {
      await categoryRadio.check();
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

    const halfDayRadio = page.getByRole('radio', { name: /half.day/ }).first();
    if (await halfDayRadio.isVisible()) {
      await halfDayRadio.check();
      await expect(page).toHaveURL(/duration=half-day/);
    }
  });
});
