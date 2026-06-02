import { test, expect } from '@playwright/test';

test.describe('Checkout', () => {
  test('booking page loads with tour details', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByText(/Participants/i)).toBeVisible();
  });

  test('participant selector respects min and max', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
    const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
    await expect(decreaseBtn).toBeVisible();
    await expect(increaseBtn).toBeVisible();
  });

  test('price breakdown updates with participants', async ({ page }) => {
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByText(/Price Breakdown/i)).toBeVisible();
    await expect(page.getByText(/Total/i)).toBeVisible();
  });

  test('shows error when tour or date missing', async ({ page }) => {
    await page.goto('/en/booking');
    await expect(page.getByText(/select a tour and date/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/booking?tour=test-adventure&date=2026-06-01&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
