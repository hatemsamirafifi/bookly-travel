import { test, expect } from '@playwright/test';

test.describe('Partner Analytics Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner/analytics');
  });

  test('should display the Analytics heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /analytics/i })).toBeVisible();
  });

  test('shows data-backed summary and chart instead of a heading-only placeholder', async ({ page }) => {
    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Total Revenue')).toBeVisible();
    await expect(page.getByText('Bookings Over Time')).toBeVisible();
    await expect(page.getByText('Conversion Rate')).toBeVisible();
    await expect(page.getByText('Not available')).toBeVisible();
  });

  test('formats minor-unit revenue and never presents unavailable conversion as zero', async ({ page }) => {
    await page.route('**/api/partner/analytics**', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        summary: { total_bookings: 2, total_revenue: 12345, average_rating: 4.5, conversion_rate: 0, conversion_rate_available: false },
        bookings_over_time: [{ date: '2026-09-01', bookings: 2, revenue: 12345 }],
        period: { from: '2026-09-01', to: '2026-09-30' },
      }),
    }));
    await page.reload();
    const revenueCard = page.getByText('Total Revenue').locator('..').locator('..');
    await expect(revenueCard).toContainText('€123.45');
    await expect(page.getByText('Not available')).toBeVisible();
    await expect(page.getByText('0.0%')).toHaveCount(0);
  });

  test('keeps analytics and partner navigation usable at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    await expect(page.getByText('Total Revenue')).toBeVisible();
    const toggle = page.getByRole('button', { name: 'Open navigation menu' });
    await toggle.click();
    await expect(page.getByRole('dialog')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(toggle).toBeFocused();
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });
});

test.describe('Partner Dashboard Summary & Charts', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner');
  });

  test('should display analytics summary cards on dashboard', async ({ page }) => {
    // Four summary cards should be visible
    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Total Revenue')).toBeVisible();
    await expect(page.getByText('Average Rating')).toBeVisible();
    await expect(page.getByText('Conversion Rate')).toBeVisible();
  });

  test('should display summary card values', async ({ page }) => {
    // Wait for the summary cards container to be visible
    await expect(page.getByText('Total Bookings')).toBeVisible();
    // Each card should have a numeric value
    const values = page.locator('.text-2xl.font-bold');
    const count = await values.count();
    expect(count).toBeGreaterThanOrEqual(4);
  });

  test('should display bookings over time chart', async ({ page }) => {
    // Chart title should be visible
    await expect(page.getByText('Bookings Over Time')).toBeVisible();

    // Recharts renders SVG elements
    const chartSvg = page.locator('.recharts-surface');
    await expect(chartSvg).toBeVisible();
  });

  test('should render chart with bookings and revenue lines', async ({ page }) => {
    // The chart should render two lines (bookings and revenue)
    // Recharts uses SVG path elements for lines
    const chartPaths = page.locator('.recharts-line-curve');
    await expect(chartPaths.first()).toBeVisible();
  });

  test('should show correct currency format in revenue card', async ({ page }) => {
    // Revenue card should show euro symbol
    const revenueCard = page.getByText('Total Revenue').locator('..');
    await expect(revenueCard).toBeVisible();

    // The value should contain a euro sign
    await expect(page.locator('.text-2xl.font-bold').filter({ hasText: /€/ })).toBeVisible();
  });

  test('does not invent a conversion percentage without a measured denominator', async ({ page }) => {
    const conversionCard = page.getByText('Conversion Rate').locator('..').locator('..');
    await expect(conversionCard.getByText('Not available')).toBeVisible();
    await expect(conversionCard.locator('.text-2xl.font-bold')).not.toContainText('%');
  });
});

test.describe('Partner Analytics - Responsive', () => {
  test('should display single-column summary cards on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/en/partner');

    // Summary cards should be visible even on small screens
    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Total Revenue')).toBeVisible();
  });

  test('should display two-column summary cards on tablet', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/en/partner');

    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Conversion')).toBeVisible();
  });
});
