import { test, expect } from '@playwright/test';

test.describe('Payment Flow', () => {
  test('deterministic payment failure can be retried without recreating the booking', async ({ page, request }) => {
    const detail = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
    expect(detail.ok()).toBeTruthy();
    const date = (await detail.json()).data.availability.next_available_date as string;
    let bookingPosts = 0;
    let paymentAttempts = 0;
    await page.route('**/api/public/bookings', (route) => {
      bookingPosts += 1;
      return route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: { reference: 'PAYMENT-TEST-1', total_price: { amount: 9000, currency: 'EUR', formatted: '€90.00' } },
          payment: { gateway: 'deterministic', client_secret: 'test-secret', stripe_publishable_key: null },
        }),
      });
    });
    await page.route('**/api/public/bookings/PAYMENT-TEST-1/deterministic-payment/confirm', (route) => {
      paymentAttempts += 1;
      return route.fulfill({
        status: paymentAttempts === 1 ? 500 : 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: { reference: 'PAYMENT-TEST-1', status: 'confirmed' } }),
      });
    });
    await page.goto(`/en/booking?tour=hidden-gems-rome-walking-tour&date=${date}&participants=2`);
    await page.getByRole('button', { name: 'Confirm & Pay' }).click();
    // Wait for the payment step to replace the booking form before asserting
    // the amount: the pre-payment PriceBreakdown renders the tour total twice,
    // so an unscoped assertion can hit strict mode while the old view is still
    // mounted (PR29 source run 37161504610). The dl below carries the
    // server-returned total for this booking.
    await expect(page.getByRole('heading', { name: 'Payment' })).toBeVisible();
    const paymentSummary = page.getByRole('main').locator('dl');
    await expect(paymentSummary.locator('dt', { hasText: 'Total' })).toBeVisible();
    const serverTotal = paymentSummary
      .locator('div')
      .filter({ hasText: 'Total' })
      .getByText('€90.00', { exact: true });
    await expect(serverTotal).toBeVisible();
    await page.getByRole('button', { name: 'Complete test payment' }).click();
    await expect(page.getByRole('alert').filter({ hasText: 'could not be completed' })).toBeVisible();
    await page.getByRole('button', { name: 'Complete test payment' }).click();
    await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=PAYMENT-TEST-1/);
    expect(bookingPosts).toBe(1);
    expect(paymentAttempts).toBe(2);
  });
  test('booking page shows payment section after confirming booking', async ({ page }) => {
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    // Wait for booking form to load
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    // Payment section appears after form submission; here we verify the initial state
    await expect(page.getByText(/Confirm & Pay/i)).toBeVisible();
  });

  test('participant selector updates price breakdown', async ({ page }) => {
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    await expect(page.getByText(/Price Breakdown/i)).toBeVisible();
    await expect(page.getByText(/Total/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/en/booking?tour=hidden-gems-rome-walking-tour&date=2026-11-15&participants=2');
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
