import { expect, test, type APIRequestContext, type Page } from '@playwright/test';

test.describe('Booking Flow', () => {
  const TOUR = 'hidden-gems-rome-walking-tour';

  /**
   * Cancel an owned booking created by the test itself. The bearer token is
   * read into memory only and never logged. Only the exact owned reference
   * is cancelled; unrelated records are never touched and no financial rows
   * are deleted. A failed cleanup annotates the exact owned reference and
   * throws so the failure is visible instead of a green pass.
   */
  async function cancelOwnedBooking(
    page: Page,
    request: APIRequestContext,
    reference: string | null,
  ): Promise<void> {
    if (!reference) return;
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));
    const response = await request.post(
      `/api/public/traveler/bookings/${encodeURIComponent(reference)}/cancel`,
      {
        data: { reason: 'E2E owned-fixture cleanup' },
        headers: token ? { Authorization: `Bearer ${token}` } : {},
      },
    );
    if (!response.ok()) {
      const description = `Could not cancel owned booking ${reference}: HTTP ${response.status()}`;
      test.info().annotations.push({
        type: 'owned-cleanup-failed',
        description,
      });
      throw new Error(description);
    }
  }

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
   * A bookable date far enough out that no other suite touches it. The date
   * is derived per-run from the backend's own availability window. Owned
   * bookings are cancelled in `finally`, releasing their held capacity.
   * Index 31 (~a month out) is used rather than index 0 so TestSprite TC002
   * (which books the next available date) never competes for the same spots.
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
    expect(dates.length).toBeGreaterThan(31);
    return dates[31];
  }

  test('completes checkout, payment, confirmation, and an idempotent replay', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    let bookingRequest: { body: string; headers: Record<string, string> } | undefined;
    let ownedReference: string | null = null;

    try {
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

    const [createResponse] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith('/api/public/bookings'),
      ),
      page.getByRole('button', { name: /Confirm & Pay/i }).click(),
    ]);
    const createBody = await createResponse.json();
    ownedReference = createBody?.data?.reference ?? null;
    expect(createResponse.ok(), `Booking creation returned HTTP ${createResponse.status()}`).toBe(true);
    expect(ownedReference).toBeTruthy();
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();

    await page.getByRole('button', { name: 'Complete test payment' }).click();
    await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=/);
    await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
    await expect(page.getByText('confirmed', { exact: true })).toBeVisible();

    expect(bookingRequest).toBeDefined();
    const originalReference = new URL(page.url()).searchParams.get('ref');
    expect(originalReference).toBe(ownedReference);

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
    } finally {
      await cancelOwnedBooking(page, request, ownedReference);
    }
  });

  for (const rejection of [
    { status: 409, expected: 'This tour date is sold out. Please select a different date.' },
    { status: 422, expected: 'Invalid booking details. Please check your selection.' },
  ]) test(`server ${rejection.status} rejects an initially valid selection without replacing it`, async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    let posts = 0;
    await page.route('**/api/public/bookings', (route) => {
      posts += 1;
      return route.fulfill({
        status: rejection.status,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Private upstream inventory details' }),
      });
    });

    const selectionUrl = `/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`;
    await page.goto(selectionUrl);
    const submit = page.getByRole('button', { name: /Confirm & Pay/i });
    await expect(submit).toBeEnabled();
    await submit.click();
    await expect(page.locator('main#main-content').getByRole('alert')).toHaveText(rejection.expected);
    await expect(page).toHaveURL(selectionUrl);
    await expect(page.getByText('Private upstream inventory details')).toHaveCount(0);
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).toHaveCount(0);
    expect(posts).toBe(1);
  });

  test('blocks an unoffered date locally without posting', async ({ page, request }) => {
    const response = await request.get(`/api/public/tours/${TOUR}?locale=en`);
    expect(response.ok()).toBeTruthy();
    const { data } = await response.json();
    const offered: string[] = data.availability.available_dates;
    expect(offered.length).toBeGreaterThan(0);
    const unoffered = new Date(`${[...offered].sort().at(-1)}T00:00:00Z`);
    unoffered.setUTCDate(unoffered.getUTCDate() + 1);
    const unofferedDate = unoffered.toISOString().slice(0, 10);
    let postCount = 0;
    await page.route('**/api/public/bookings', async (route) => {
      postCount += 1;
      await route.continue();
    });

    await page.goto(`/en/booking?tour=${TOUR}&date=${unofferedDate}&participants=2`);
    await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
    await expect(page.getByText('Select an offered date to continue.')).toBeVisible();
    expect(postCount).toBe(0);
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

  test('retains a supplied invalid participant value with a reselection route instead of clamping', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=0`);

    await expect(page.getByText(/Invalid booking details/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
    const changeLinks = page.locator('main#main-content').getByRole('link', { name: /Change date/i });
    expect(await changeLinks.count()).toBeGreaterThan(0);
    for (const link of await changeLinks.all()) {
      await expect(link).toHaveAttribute('href', `/en/tours/${TOUR}`);
    }
  });

  test('retains a supplied malformed date with a reselection route and blocks submit', async ({ page }) => {
    await page.goto(`/en/booking?tour=${TOUR}&date=not-a-date&participants=2`);

    await expect(page.getByText(/Invalid booking details/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Confirm & Pay/i })).toBeDisabled();
    await expect(page.getByRole('link', { name: /Change date/i })).toHaveAttribute(
      'href',
      `/en/tours/${TOUR}`,
    );
  });

  test('expired session recovers through login with the full selection and no duplicate submit', async ({ page, request }) => {
    const availableDate = await futureBookableDate(request);
    let postCount = 0;
    let ownedReference: string | null = null;
    try {
    await page.route('**/api/public/bookings', async (route) => {
      postCount += 1;
      if (postCount === 1) {
        await route.fulfill({
          status: 401,
          contentType: 'application/json',
          body: JSON.stringify({ message: 'Unauthenticated.' }),
        });
      } else {
        await route.continue();
      }
    });
    await page.goto(`/en/booking?tour=${TOUR}&date=${availableDate}&participants=2`);
    await page.getByRole('button', { name: /Confirm & Pay/i }).click();

    // Bounded recovery: safe login keeps the full selection and notices expiry.
    await expect(page).toHaveURL(/\/en\/auth\/login\?sessionExpired=1&returnUrl=/);
    expect(new URL(page.url()).searchParams.get('returnUrl')).toBe(
      `/en/booking?tour=${TOUR}&participants=2&date=${availableDate}`,
    );
    await expect(page.getByText(/session has expired/i)).toBeVisible();

    // Re-authenticate with the seeded traveler; the full query returns intact.
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(new RegExp(`/en/booking\\?tour=${TOUR}`));

    // Selections intact with no automatic submission after recovery.
    await expect(page.getByText('2', { exact: true })).toBeVisible();
    expect(postCount).toBe(1);

    // Explicit resubmit proceeds to the deterministic payment step. The owned
    // reference is captured synchronously from the successful create response
    // so a later payment failure still leaves the exact owned reference.
    const [createResponse] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith('/api/public/bookings'),
      ),
      page.getByRole('button', { name: /Confirm & Pay/i }).click(),
    ]);
    const createBody = await createResponse.json();
    ownedReference = createBody?.data?.reference ?? null;
    expect(createResponse.ok(), `Booking creation returned HTTP ${createResponse.status()}`).toBe(true);
    expect(ownedReference).toBeTruthy();
    await expect(page.getByText('Local test gateway. No card or external charge is used.')).toBeVisible();
    expect(postCount).toBe(2);

    // Complete the deterministic payment on the same reference before cleanup:
    // cancellation requires a confirmed booking, not pending_payment.
    await page.getByRole('button', { name: 'Complete test payment' }).click();
    await expect(page).toHaveURL(/\/en\/booking\/confirmation\?ref=/);
    await expect(page.getByRole('heading', { name: 'Booking Confirmed!' })).toBeVisible();
    expect(new URL(page.url()).searchParams.get('ref')).toBe(ownedReference);
    } finally {
      await cancelOwnedBooking(page, request, ownedReference);
    }
  });
});
