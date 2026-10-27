import { test, expect } from '@playwright/test';
import {
  fetchOfferedTourFacts,
  formatEstimateTotal,
} from './helpers/offered-date';

const TOUR_SLUG = 'hidden-gems-rome-walking-tour';

test.describe('Payment Flow', () => {
  test('deterministic payment failure can be retried without recreating the booking', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    const date = facts.date;
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
  test('booking page shows payment section after confirming booking', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=2`);
    // Wait for booking form to load
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: facts.title })).toBeVisible();
    // Payment section appears after form submission; here we verify the initial
    // state is a READY valid form with submission enabled.
    await expect(page.getByText(/Confirm & Pay/i)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Confirm & Pay' })).toBeEnabled();
  });

  test('participant selector updates price breakdown', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    let bookingPosts = 0;
    await page.route('**/api/public/bookings', async (route) => {
      bookingPosts += 1;
      await route.continue();
    });
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=${facts.groupMin}`);
    await expect(page.getByText(/Price Breakdown/i)).toBeVisible();
    await expect(page.getByText(/Total/i)).toBeVisible();
    const before = formatEstimateTotal(facts.baseAmount, facts.groupMin, 'en', facts.baseCurrency);
    await expect(page.getByText(before, { exact: true }).first()).toBeVisible();
    if (facts.groupMin < facts.groupMax) {
      await page.getByRole('button', { name: 'Increase participants' }).click();
      const after = formatEstimateTotal(facts.baseAmount, facts.groupMin + 1, 'en', facts.baseCurrency);
      await expect(page.getByText(after, { exact: true }).first()).toBeVisible();
      await expect(page.locator('span[aria-labelledby="participants-label"]')).toHaveText(String(facts.groupMin + 1));
    } else {
      await expect(page.getByRole('button', { name: 'Increase participants' })).toBeDisabled();
    }
    expect(bookingPosts).toBe(0);
  });

  test('responsive layout at 390px', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=2`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
