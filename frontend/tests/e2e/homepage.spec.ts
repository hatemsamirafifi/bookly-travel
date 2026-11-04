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
    const firstCover = page.locator('section').filter({ has: page.getByRole('heading', { name: 'Featured Tours', exact: true }) }).locator('a[href*="/tours/"] img').first();
    await expect(firstCover).toHaveAttribute('loading', 'eager');
    await expect(firstCover).toHaveAttribute('fetchpriority', 'high');
  });

  test('locale switching works', async ({ page }) => {
    await page.goto('/en');
    await page.waitForLoadState('networkidle');
    await page.getByRole('contentinfo').getByRole('combobox', { name: 'Switch language' }).selectOption('es');
    await expect(page).toHaveURL(/\/es/);
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/');
    await page.goto('/en');
    await expect(page.getByRole('heading', { name: /Discover & Book Amazing Tours/i })).toBeVisible();
  });

  test('homepage search reaches results and browser Back restores discovery', async ({ page }) => {
    await page.goto('/en');
    await expect(page).toHaveTitle('Bookly — Discover & Book Amazing Tours');
    const search = page.getByRole('searchbox', { name: 'Search tours' });
    await search.fill('Rome');
    await search.press('Enter');
    await expect(page).toHaveURL(/\/en\/search\?q=Rome/);
    await page.goBack();
    await expect(page).toHaveURL(/\/en\/?$/);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('Spanish and Italian discovery headings use their locale', async ({ page }) => {
    await page.goto('/es');
    await expect(page).toHaveTitle('Bookly — Descubre y Reserva Tours Increíbles');
    await expect(page.getByRole('heading', { level: 1, name: 'Descubre y Reserva Tours Increíbles' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Categorías Populares' })).toBeVisible();

    await page.goto('/it');
    await expect(page).toHaveTitle('Bookly — Scopri e Prenota Tour Incredibili');
    await expect(page.getByRole('heading', { level: 1, name: 'Scopri e Prenota Tour Incredibili' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Categorie Popolari' })).toBeVisible();
  });

  test('partner invitation stays secondary and links to the public registration route', async ({ page }) => {
    await page.goto('/en');
    const partnerLink = page.getByRole('link', { name: 'Become a partner' });
    await expect(partnerLink).toHaveAttribute('href', '/en/partner-register');
    await expect(partnerLink).toBeVisible();
  });

  test('category chip navigates to filtered search with the category pre-applied', async ({ page }) => {
    await page.goto('/en');
    await expect(page.locator('section[aria-label="Tour Categories"]')).toBeVisible();
    const firstChip = page.locator('section[aria-label="Tour Categories"] a').first();
    await expect(firstChip).toBeVisible();

    const chipHref = await firstChip.getAttribute('href');
    expect(chipHref).toMatch(/^\/en\/search\?category=/);
    const expectedSlug = new URLSearchParams(chipHref!.split('?')[1] ?? '').get('category');
    expect(expectedSlug).toBeTruthy();

    await firstChip.click();
    await expect(page).toHaveURL(/\/en\/search\?category=/);
    expect(new URL(page.url()).searchParams.get('category')).toBe(expectedSlug);

    // The applied category surfaces in the active-filters region above results.
    const activeChips = page.locator('div[role="region"][aria-label*="filter" i]');
    await expect(activeChips).toBeVisible();

    // At least one result card renders, or the page honestly reports no tours
    // for this category (mirrors the non-brittle assertions in search.spec.ts).
    const firstResult = page.locator('a[href*="/tours/"]').first();
    const emptyState = page.getByRole('heading', { name: 'No tours found' });
    await expect(firstResult.or(emptyState)).toBeVisible({ timeout: 10000 });
  });

  test('bookly value panel displays marketplace guarantees', async ({ page }) => {
    await page.goto('/en');
    const valuePanel = page.locator('section[aria-labelledby="value-panel-heading"]');
    await expect(valuePanel).toBeVisible();
    await expect(valuePanel.getByText('Verified Local Guides')).toBeVisible();
    await expect(valuePanel.getByText('Secure Instant Booking')).toBeVisible();
  });
});
