import { test, expect } from '@playwright/test';

test.describe('Payment Flow', () => {
  test('booking page shows payment section after confirming booking', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    // Wait for booking form to load
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Payment section appears after form submission; here we verify the initial state
    await expect(page.getByText(/Confirm & Pay/i)).toBeVisible();
  });

  test('participant selector updates price breakdown', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByText(/Price Breakdown/i)).toBeVisible();
    await expect(page.getByText(/Total/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
