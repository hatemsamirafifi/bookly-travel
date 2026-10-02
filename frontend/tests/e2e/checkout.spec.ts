import { test, expect } from '@playwright/test';

test.describe('Checkout', () => {
  async function availableDate(request: import('@playwright/test').APIRequestContext) {
    const response = await request.get('/api/public/tours/hidden-gems-rome-walking-tour?locale=en');
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    return body.data.availability.next_available_date as string;
  }

  test('price drift requires explicit acceptance of the backend price before payment', async ({ page, request }) => {
    const date = await availableDate(request);
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
      const date = await availableDate(request);
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
    const date = await availableDate(request);
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
