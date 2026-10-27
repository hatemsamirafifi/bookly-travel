import { test, expect } from '@playwright/test';
import {
  fetchOfferedTourFacts,
  formatEstimateTotal,
} from './helpers/offered-date';

const TOUR_SLUG = 'hidden-gems-rome-walking-tour';

test.describe('Checkout', () => {
  test('price drift requires explicit acceptance of the backend price before payment', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    const date = facts.date;
    await page.route('**/api/public/bookings', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        price_changed: true,
        data: {
          reference: 'DRIFT-TEST-1',
          pricing: { price_per_person: { amount: 5500, currency: 'EUR', formatted: '€55.00' }, total: { amount: 11000, currency: 'EUR', formatted: '€110.00' } },
        },
        payment: { gateway: 'deterministic', client_secret: 'test-secret', stripe_publishable_key: null },
      }),
    }));
    await page.goto(`/en/booking?tour=hidden-gems-rome-walking-tour&date=${date}&participants=2`);
    await page.getByRole('button', { name: 'Confirm & Pay' }).click();
    const dialog = page.getByRole('dialog', { name: 'Price has changed' });
    await expect(dialog).toBeVisible();
    await expect(dialog).toContainText('€55.00');
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).not.toBeVisible();
    await dialog.getByRole('button', { name: 'Confirm at new price' }).click();
    await expect(dialog).not.toBeVisible();
    await expect(page.getByText('€110.00')).toBeVisible();
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();
  });

  for (const [locale, dateLabel, summaryLabel] of [
    ['en', 'Selected Date', 'Price Breakdown'],
    ['es', 'Fecha Seleccionada', 'Desglose de Precios'],
    ['it', 'Data Selezionata', 'Riepilogo Prezzi'],
  ]) {
    test(`${locale} checkout shows the localized tour summary before payment`, async ({ page, request }) => {
      const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, locale);
      const date = facts.date;
      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(`/${locale}/booking?tour=hidden-gems-rome-walking-tour&date=${date}&participants=2`);
      await expect(page.getByText(dateLabel, { exact: true })).toBeVisible();
      await expect(page.getByRole('heading', { name: summaryLabel })).toBeVisible();
      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
    });
  }

  test('failed cancellation of a drifted booking keeps the decision visible', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    const date = facts.date;
    await page.route('**/api/public/bookings', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        price_changed: true,
        data: { reference: 'DRIFT-TEST-2', pricing: { price_per_person: { amount: 5500, currency: 'EUR', formatted: '€55.00' } } },
        payment: { gateway: 'deterministic', client_secret: 'test-secret', stripe_publishable_key: null },
      }),
    }));
    await page.route('**/api/public/traveler/bookings/DRIFT-TEST-2/cancel', (route) => route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Cancel failed' }) }));
    await page.goto(`/en/booking?tour=hidden-gems-rome-walking-tour&date=${date}&participants=2`);
    await page.getByRole('button', { name: 'Confirm & Pay' }).click();
    const dialog = page.getByRole('dialog', { name: 'Price has changed' });
    await dialog.getByRole('button', { name: 'Cancel' }).click();
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('alert')).toContainText('Could not cancel');
  });
  test('booking page loads with tour details', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=2`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.getByRole('heading', { name: facts.title })).toBeVisible();
    // Exact match: the ParticipantSelector label is "Participants", but PriceBreakdown
    // renders "{n} participants" — a substring /Participants/i regex matches both and
    // trips strict mode. Match the label only.
    await expect(page.getByText('Participants', { exact: true })).toBeVisible();
  });

  test('participant selector respects min and max', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    let bookingPosts = 0;
    await page.route('**/api/public/bookings', async (route) => {
      bookingPosts += 1;
      await route.continue();
    });
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=${facts.groupMin}`);
    const decreaseBtn = page.getByRole('button', { name: 'Decrease participants' });
    const increaseBtn = page.getByRole('button', { name: 'Increase participants' });
    const count = page.locator('span[aria-labelledby="participants-label"]');
    // At the actual minimum the stepper shows the minimum, cannot go lower,
    // and quotes exactly the per-person base.
    await expect(count).toHaveText(String(facts.groupMin));
    await expect(decreaseBtn).toBeDisabled();
    await expect(page.getByText(formatEstimateTotal(facts.baseAmount, facts.groupMin, 'en', facts.baseCurrency), { exact: true }).first()).toBeVisible();
    // Drive to the actual maximum: the count and the exact estimate follow,
    // then the stepper cannot go higher. No booking POST is ever issued.
    for (let n = facts.groupMin; n < facts.groupMax; n += 1) {
      await increaseBtn.click();
    }
    await expect(count).toHaveText(String(facts.groupMax));
    await expect(increaseBtn).toBeDisabled();
    await expect(page.getByText(formatEstimateTotal(facts.baseAmount, facts.groupMax, 'en', facts.baseCurrency), { exact: true }).first()).toBeVisible();
    expect(bookingPosts).toBe(0);
  });

  test('price breakdown updates with participants', async ({ page, request }) => {
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
    } else {
      await expect(page.getByRole('button', { name: 'Increase participants' })).toBeDisabled();
    }
    expect(bookingPosts).toBe(0);
  });

  test('shows error when tour or date missing', async ({ page }) => {
    await page.goto('/en/booking');
    await expect(page.getByText(/select a tour and date/i)).toBeVisible();
  });

  test('responsive layout at 390px', async ({ page, request }) => {
    const facts = await fetchOfferedTourFacts(request, TOUR_SLUG, 'en');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/en/booking?tour=${facts.slug}&date=${facts.date}&participants=2`);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
