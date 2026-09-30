import { expect, test, type APIRequestContext } from '@playwright/test';

test.describe('Booking Flow', () => {
  const TOUR = 'hidden-gems-rome-walking-tour';
  const SOLD_OUT_DATE = '2026-12-01';

  for (const [status, heading] of [
    ['pending_payment', 'Payment Pending'],
    ['cancelled', 'Booking Cancelled'],
    ['expired', 'Booking Expired'],
    ['unexpected_status', 'Status'],
  ]) {
    test(`${status} confirmation never claims a completed booking`, async ({ page }) => {
      const reference = `STATE-TEST-${status}`;
      await page.route(`**/api/public/traveler/bookings/${reference}`, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: {
          reference,
          status,
          tour: { name: 'Test tour', title: 'Test tour' },
          tour_date: '2026-11-15',
          participants: 2,
          pricing: { total: { amount: 9000, currency: 'EUR', formatted: '€90.00' } },
          cancellation_policy: 'Cancellation terms apply.',
        } }),
      }));
      await page.goto(`/en/booking/confirmation?ref=${reference}`);
      await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toHaveCount(0);
      await expect(page).not.toHaveTitle(/Booking Confirmed/);
    });
  }

  test('confirmed booking alone displays the success title', async ({ page }) => {
    await page.route('**/api/public/traveler/bookings/STATE-TEST-CONFIRMED', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ data: {
        reference: 'STATE-TEST-CONFIRMED',
        status: 'confirmed',
        tour: { name: 'Test tour' },
        tour_date: '2026-11-15',
        participants: 2,
        pricing: { total: { amount: 9000, currency: 'EUR', formatted: '€90.00' } },
      } }),
    }));
    await page.goto('/en/booking/confirmation?ref=STATE-TEST-CONFIRMED');
    await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
    await expect(page.getByText('€90.00')).toBeVisible();
  });

  /**
   * A bookable date far enough out that no other suite touches it. Fixed
   * calendar dates rot: every confirmed booking permanently consumes fixture
   * capacity (e.g. 2026-11-15 sold out after repeated runs), so the date is
   * derived per-run from the backend's own availability window. Index 30
   * (~a month out) is used rather than index 0 so TestSprite TC002 (which
   * books the next available date) never competes for the same spots, and
   * the rolling window spreads consumption across dates over time.
   */
  async function futureBookableDate(request: APIRequestContext): Promise<string> {
    const res = await request.get(`/api/public/tours/${TOUR}?locale=en`);
    if (!res.ok()) {
      throw new Error(`tour detail API returned ${res.status()}`);
    }
    const body = await res.json();
    const dates: string[] = body?.data?.availability?.available_dates ?? [];
    if (dates.length === 0) {
      throw new Error('tour has no available dates');
    }
    return dates[Math.min(30, dates.length - 1)];
  }

  test('completes checkout, payment, confirmation, and an idempotent replay', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    let bookingRequest: { body: string; headers: Record<string, string> } | undefined;

    page.on('request', (outgoingRequest) => {
      if (outgoingRequest.method() !== 'POST' || !outgoingRequest.url().endsWith('/api/public/bookings')) {
        return;
      }

      bookingRequest = {
        body: outgoingRequest.postData() ?? '{}',
        headers: outgoingRequest.headers(),
      };
    });

    await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
    await expect(page.getByText('2', { exact: true })).toBeVisible();

    await page.getByRole('button', { name: /Confirm & Pay/i }).click();
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();

    await page.getByRole('button', { name: 'Complete test payment' }).click();
    await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=/);
    await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
    await expect(page.getByText('confirmed', { exact: true })).toBeVisible();

    expect(bookingRequest).toBeDefined();
    const originalReference = new URL(page.url()).searchParams.get('ref');
    expect(originalReference).toBeTruthy();

    const replayResponse = await request.post('/api/public/bookings', {
      data: JSON.parse(bookingRequest!.body),
      headers: {
        Authorization: bookingRequest!.headers.authorization,
        'Content-Type': 'application/json',
        'Idempotency-Key': bookingRequest!.headers['idempotency-key'],
      },
    });
    expect(replayResponse.ok()).toBeTruthy();
    const replay = await replayResponse.json();
    expect(replay.data.reference).toBe(originalReference);

    await page.reload();
    await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
    await expect(page.getByText(originalReference!, { exact: true })).toBeVisible();
  });

  test('shows error for sold out tour', async ({ page }) => {
    await page.goto(`/en/booking?tour=${TOUR}&date=${SOLD_OUT_DATE}`);

    await page.getByRole('button', { name: /Confirm & Pay/i }).click();
    await expect(page.getByText(/sold out/i)).toBeVisible();
  });

  test('shows the localized rate limit message', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    await page.route('**/api/public/bookings', (route) =>
      route.fulfill({
        status: 429,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Too Many Attempts.' }),
      }),
    );

    await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
    await page.getByRole('button', { name: /Confirm & Pay/i }).click();

    await expect(page.getByRole('alert').filter({ hasText: 'Too many booking attempts.' })).toHaveText(
      'Too many booking attempts. Please wait a moment and try again.',
    );
  });

  test('clamps an invalid participant query value to the tour minimum', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=0`);

    const participantValue = page.locator('[aria-labelledby="participants-label"]');
    await expect(participantValue).toHaveText('1');
    await expect(page.getByRole('button', { name: 'Decrease participants' })).toBeDisabled();
    await expect(page.getByText('1–20 allowed')).toBeVisible();
  });
});
