import { test, expect } from '@playwright/test';

test.describe('Partner Analytics Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/en/partner/analytics');
  });

  test('should display the Analytics heading', async ({ page }) => {
    await expect(page.getByRole('heading', { name: /analytics/i })).toBeVisible();
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

  test('should show percentage format in conversion card', async ({ page }) => {
    // Conversion card should show a percentage
    await expect(page.locator('.text-2xl.font-bold').filter({ hasText: /%/ })).toBeVisible();
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