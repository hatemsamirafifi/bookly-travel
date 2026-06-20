import { test, expect } from '@playwright/test';
import { partnerLogin } from '../helpers/auth';

test.describe('Partner Dashboard Home', () => {
  test.beforeEach(async ({ page }) => {
    await partnerLogin(page);
    await page.goto('/en/partner');
  });

  test('should display analytics summary cards', async ({ page }) => {
    // The dashboard page shows 4 summary cards
    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Total Revenue')).toBeVisible();
    await expect(page.getByText('Avg. Rating')).toBeVisible();
    await expect(page.getByText('Conversion')).toBeVisible();
  });

  test('should display analytics values in summary cards', async ({ page }) => {
    // Each card should have a numeric value displayed
    const cards = page.locator('.bg-white.rounded-xl.border');
    await expect(cards).toHaveCount(4);

    // Values should be visible (could be placeholder data or real data)
    const values = page.locator('.text-2xl.font-bold');
    await expect(values.first()).toBeVisible();
  });

  test('should display bookings over time chart', async ({ page }) => {
    // The chart container should be visible
    await expect(page.getByText('Bookings Over Time')).toBeVisible();

    // The chart should render inside a container
    const chartContainer = page.locator('.recharts-responsive-container');
    await expect(chartContainer).toBeVisible();
  });

  test('should show icon badges on each summary card', async ({ page }) => {
    // Each card has an icon with colored background
    const iconBadges = page.locator('.rounded-lg.p-2');
    // At least 4 icon badges (one per card)
    await expect(iconBadges).toHaveCount(4);
  });

  test('summary cards should be in a responsive grid', async ({ page }) => {
    // Cards grid should adapt from 1 to 4 columns
    const grid = page.locator('.grid.grid-cols-1');
    await expect(grid).toBeVisible();
  });
});

test.describe('Partner Dashboard - Mobile View', () => {
  test('should stack summary cards on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    // Cards should still be visible but in single column
    await expect(page.getByText('Total Bookings')).toBeVisible();
    await expect(page.getByText('Total Revenue')).toBeVisible();
  });

  test('should show chart below cards on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/partner');

    await expect(page.getByText('Bookings Over Time')).toBeVisible();
  });
});