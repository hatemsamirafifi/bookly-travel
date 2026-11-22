import { test, expect, type Browser, type BrowserContext } from '@playwright/test';
import {
  apiGet,
  apiPost,
  buildOrderedMedia,
  createIsolatedTravelerContext,
  LONG_DESCRIPTION,
  uniqueTitle,
} from './tour-content-helpers';

/**
 * EGM-005 Wave 3E — Confirmation, Detail, Voucher PDF, /v/[reference] & Lifecycle E2E.
 *
 * Covers:
 * 1. Tiered departure booking confirmation with explicit departure time and tier snapshot.
 * 2. Traveler booking detail view (/en/my-bookings/[ref]) with departure, rate tier, and PDF voucher download.
 * 3. Voucher PDF generation (DomPDF / BaconQrCode) returns HTTP 200 application/pdf with %PDF- header.
 * 4. Public verification page (/v/[ref]) displays valid status and zero PII.
 * 5. Partner booking detail (/en/partner/bookings/[ref]) displays departure time, option, and tier rate.
 * 6. Traveler cancellation releases seat capacity and reflects CANCELLED on /v/[ref].
 */
test.describe.configure({ mode: 'serial', timeout: 150000 });

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';

function addDaysIso(days: number): string {
  const date = new Date(Date.now() + days * 86_400_000);
  return date.toISOString().slice(0, 10);
}

async function filamentPublishTour(browser: Browser, tourId: number): Promise<void> {
  const adminContext = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    isMobile: false,
    hasTouch: false,
    storageState: { cookies: [], origins: [] },
  });

  try {
    const adminPage = await adminContext.newPage();
    await adminPage.goto('/admin/login');
    const email = adminPage.getByLabel(/email address/i).first();
    await email.waitFor({ state: 'visible', timeout: 30000 });
    await email.fill(ADMIN_EMAIL);
    await adminPage.getByLabel(/password/i).first().fill(ADMIN_PASSWORD);
    await Promise.all([
      adminPage.waitForURL('**/admin', { timeout: 30000 }),
      adminPage.getByRole('button', { name: /sign in/i }).click(),
    ]);

    await adminPage.goto('/admin/tours?tableSortColumn=id&tableSortDirection=desc');
    const row = adminPage
      .getByRole('row')
      .filter({ has: adminPage.getByRole('cell', { name: String(tourId), exact: true }) });
    await expect(row).toBeVisible({ timeout: 30000 });
    await row.getByRole('button', { name: 'Publish', exact: true }).click();
    const dialog = adminPage.locator('.fi-modal-open .fi-modal-window');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(dialog).toBeHidden();
    await expect(adminPage.getByText('Tour published', { exact: true })).toBeVisible();
  } finally {
    await adminContext.close().catch(() => {});
  }
}

test('Wave 3E: End-to-end booking voucher, confirmation, detail, partner view, and cancellation lifecycle', async ({
  page: partnerPage,
  browser,
}) => {
  const createdTourIds: number[] = [];
  const createdRefs: string[] = [];
  let travelerContext: BrowserContext | null = null;

  try {
    await partnerPage.goto('/en/partner');

    // 1) Partner creates a tour with active option, departures, and tiered pricing
    const title = uniqueTitle('Wave3E Voucher Lifecycle');
    const createResp = await apiPost(partnerPage, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Florence, Italy',
      duration_value: 3,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      guide_languages: ['en'],
      media: buildOrderedMedia(2, 'egm005-voucher'),
      options: [
        {
          title: 'Lifecycle Morning Option',
          pickup_instructions: 'Meet outside central cathedral doors',
          guide_languages: ['en'],
          price_amount: 9000,
          group_size_max: 12,
          start_time: '10:00',
          weekdays: [0, 1, 2, 3, 4, 5, 6],
        },
      ],
      translations: {
        en: {
          title,
          description: LONG_DESCRIPTION,
          highlights: ['Skip-the-line entry'],
          inclusions: ['Local guide', 'Wine tasting'],
          exclusions: ['Gratuities'],
          important_information: ['Arrive 10 minutes early'],
          meeting_point: 'Piazza del Duomo 1, Florence',
          cancellation_policy: 'Free cancellation up to 24 hours before start.',
          itinerary: [
            {
              day: 1,
              title: 'Historic Core',
              description: null,
              stops: [{ title: 'Colosseum Exterior', description: null, duration_minutes: 45 }],
            },
          ],
        },
      },
    });
    expect(createResp.status).toBe(201);
    const tourId = (createResp.json as { data: { id: number; slug: string } }).data.id;
    const slug = (createResp.json as { data: { slug: string } }).data.slug;
    createdTourIds.push(tourId);

    const tierRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/pricing`, {
      name: 'Base Standard',
      price: 90,
      min_participants: 1,
      max_participants: 12,
    });
    expect(tierRes.status).toBe(201);

    const ruleRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/availability/rules`, {
      rule_type: 'recurring',
      days_of_week: [0, 1, 2, 3, 4, 5, 6],
      start_time: '10:00',
      start_date: addDaysIso(1),
      end_date: addDaysIso(160),
      capacity: 8,
    });
    expect(ruleRes.status).toBe(201);

    const submitRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/submit`, {});
    expect(submitRes.status).toBe(200);

    await filamentPublishTour(browser, tourId);

    const detail = await apiGet(partnerPage, `/api/partner/tours/${tourId}`);
    expect(detail.status).toBe(200);
    const optionId = (detail.json as { data: { options: Array<{ id: number }> } }).data.options[0].id;

    // Create departure on day 16
    const targetDate = addDaysIso(16);
    const depResp = await apiPost(partnerPage, `/api/partner/tours/${tourId}/departures`, {
      tour_option_id: optionId,
      local_date: targetDate,
      local_time: '10:00',
      timezone: 'Europe/Rome',
      capacity: 8,
      booking_cutoff_hours: 2,
    });
    expect(depResp.status).toBe(201);
    const departureId = (depResp.json as { data: { id: number } }).data.id;

    // Sync option tiered pricing
    const syncResp = await apiPost(
      partnerPage,
      `/api/partner/tours/${tourId}/pricing/sync`,
      {
        tour_option_id: optionId,
        pricing_mode: 'per_person',
        tiers: [
          { min_participants: 1, max_participants: 1, price_amount: 9000, name: 'Solo' },
          { min_participants: 2, max_participants: 12, price_amount: 7000, name: 'Small Group' },
        ],
      },
    );
    expect(syncResp.status).toBe(200);

    // 2) Traveler books on this departure (isolated disposable traveler avoids
    // shared per-user booking.create rate-limit buckets without Redis FLUSHALL)
    travelerContext = await createIsolatedTravelerContext(browser);
    const travelerPage = await travelerContext.newPage();

    await travelerPage.goto(
      `/en/booking?tour=${slug}&option=${optionId}&participants=2&date=${targetDate}`,
    );

    const depFieldset = travelerPage.getByTestId('booking-departures');
    await expect(depFieldset).toBeVisible({ timeout: 20000 });
    await expect(depFieldset).toContainText('10:00 (Europe/Rome)');

    const createPromise = travelerPage.waitForResponse(
      (resp) => resp.request().method() === 'POST' && resp.url().endsWith('/api/public/bookings'),
    );
    await travelerPage.getByRole('button', { name: 'Confirm & Pay' }).click();
    const createRes = await createPromise;
    expect(createRes.status()).toBe(201);

    const bookingData = (await createRes.json()) as {
      data: {
        reference: string;
        departure?: { id: number };
        pricing_snapshot?: { unit_price_cents: number; total_price_cents: number };
      };
    };
    const reference = bookingData.data.reference;
    createdRefs.push(reference);
    expect(bookingData.data.departure?.id).toBe(departureId);

    // Complete test payment
    await travelerPage.getByRole('button', { name: 'Complete test payment' }).click();
    await travelerPage.waitForURL(`**/booking/confirmation?ref=${reference}`, { timeout: 30000 });

    // 3) Confirmation view assertions
    await expect(travelerPage.getByText(reference)).toBeVisible();
    await expect(travelerPage.getByText('10:00 (Europe/Rome)')).toBeVisible();
    await expect(travelerPage.getByText('€140.00')).toBeVisible();

    // 4) Traveler booking detail view (/en/my-bookings/[ref])
    await travelerPage.goto(`/en/my-bookings/${reference}`);
    await expect(travelerPage.getByText(reference)).toBeVisible({ timeout: 20000 });
    await expect(travelerPage.getByText('10:00 (Europe/Rome)')).toBeVisible();
    await expect(travelerPage.getByText('Lifecycle Morning Option')).toBeVisible();

    const travelerToken = await travelerPage.evaluate(() => localStorage.getItem('auth_token'));
    expect(travelerToken).toBeTruthy();

    // 5) Voucher PDF download endpoint
    const voucherResp = await travelerPage.request.get(
      `/api/public/traveler/bookings/${reference}/voucher`,
      {
        headers: {
          Authorization: `Bearer ${travelerToken}`,
        },
      },
    );
    expect(voucherResp.status()).toBe(200);
    expect(voucherResp.headers()['content-type']).toContain('application/pdf');
    const voucherBody = await voucherResp.body();
    expect(voucherBody.subarray(0, 5).toString('ascii')).toBe('%PDF-');

    // 6) Unauthenticated public verification page (/v/[ref])
    const unauthedContext = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    try {
      const verifyPage = await unauthedContext.newPage();
      const vResp = await verifyPage.goto(`/v/${reference}`);
      expect(vResp?.status()).toBe(200);
      await expect(verifyPage.getByText(reference)).toBeVisible({ timeout: 15000 });
      await expect(verifyPage.getByText(title)).toBeVisible();
      // Verify zero PII exposed (neither seeded nor isolated traveler email leaks)
      const bodyText = await verifyPage.textContent('body');
      expect(bodyText).not.toContain('admin@bookly.test');
      expect(bodyText).not.toContain('alice@example.com');
      expect(bodyText).not.toContain('traveler@bookly.test');
      expect(bodyText).not.toContain('e2e-traveler-');
      expect(bodyText).not.toContain('@bookly.test');
    } finally {
      await unauthedContext.close();
    }

    // 7) Partner portal booking detail
    await partnerPage.goto(`/en/partner/bookings`);
    await partnerPage.goto(`/en/partner/bookings/${reference}`);
    await expect(partnerPage.getByText(reference)).toBeVisible({ timeout: 20000 });
    await expect(partnerPage.getByText('10:00 (Europe/Rome)')).toBeVisible();
    await expect(partnerPage.getByText('Lifecycle Morning Option')).toBeVisible();
    await expect(partnerPage.getByText('€140.00')).toBeVisible();

    // 8) Cancellation lifecycle: traveler cancels booking
    const cancelResp = await travelerPage.request.post(
      `/api/public/traveler/bookings/${reference}/cancel`,
      {
        headers: {
          Authorization: `Bearer ${travelerToken}`,
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        data: JSON.stringify({ reason: 'Schedule conflict' }),
      },
    );
    expect(cancelResp.status()).toBe(200);

    // Verify /v/[reference] updates to cancelled
    const verifyAfterCancelContext = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    try {
      const verifyPage2 = await verifyAfterCancelContext.newPage();
      await verifyPage2.goto(`/v/${reference}`);
      await expect(verifyPage2.getByText(reference)).toBeVisible({ timeout: 15000 });
      await expect(verifyPage2.locator('body')).toContainText(/cancelled/i);
    } finally {
      await verifyAfterCancelContext.close();
    }

    // Verify departure capacity is restored on public departures endpoint
    const depsCheck = await apiGet(
      partnerPage,
      `/api/public/tours/${slug}/departures?date_from=${targetDate}&date_to=${targetDate}`,
    );
    expect(depsCheck.status).toBe(200);
    const finalDepData = (depsCheck.json as { data: Array<{ id: number; available_spots: number }> }).data;
    const matchedDep = finalDepData.find((d) => d.id === departureId);
    expect(matchedDep?.available_spots).toBe(8);
  } finally {
    if (travelerContext) {
      await travelerContext.close().catch(() => {});
    }
    // Clean up created tours
    for (const tid of createdTourIds) {
      await apiPost(partnerPage, `/api/partner/tours/${tid}/archive`, {}).catch(() => {});
    }
  }
});
