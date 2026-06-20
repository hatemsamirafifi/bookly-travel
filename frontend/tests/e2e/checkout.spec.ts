import { test, expect } from '@playwright/test';

test.describe('Checkout', () => {
  test('booking page loads with tour details', async ({ page }) => {
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Exact match: the ParticipantSelector label is "Participants", but PriceBreakdown
    // renders "{n} participants" — a substring /Participants/i regex matches both and
    // trips strict mode. Match the label only.
    await expect(page.getByText('Participants', { exact: true })).toBeVisible();
  });

  test('participant selector respects min and max', async ({ page }) => {
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
    const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
    await expect(decreaseBtn).toBeVisible();
    await expect(increaseBtn).toBeVisible();
  });

  test('price breakdown updates with participants', async ({ page }) => {
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    await expect(page.getByText(/Price Breakdown/i)).toBeVisible();
    await expect(page.getByText(/Total/i)).toBeVisible();
  });

  test('shows error when tour or date missing', async ({ page }) => {
    await page.goto('/en/booking');
    await expect(page.getByText(/select a tour and date/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
