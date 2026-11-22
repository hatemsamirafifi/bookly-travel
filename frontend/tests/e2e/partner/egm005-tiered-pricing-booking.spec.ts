import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {
  apiGet,
  apiPost,
  buildOrderedMedia,
  createIsolatedTravelerContext,
  LONG_DESCRIPTION,
  uniqueTitle,
} from './tour-content-helpers';

/**
 * EGM-005 Wave 3D — Tiered Pricing, Explicit Departure Checkout & Price-Drift Decline E2E (D5).
 *
 * Tests fullstack flow across Next.js partner pricing UI, Laravel integer-cents tiered pricing
 * API, public tour detail dynamic pricing table, traveler checkout departure selection,
 * zero false price drift, real price-drift modal + POST /decline seat release, and deterministic confirmation.
 */
test.describe.configure({ mode: 'serial', timeout: 150000 });

const ADMIN_EMAIL = 'admin@bookly.test';
const ADMIN_PASSWORD = 'password';

function addDaysIso(days: number): string {
  const date = new Date(Date.now() + days * 86_400_000);
  return date.toISOString().slice(0, 10);
}

async function addConsentCookies(context: BrowserContext): Promise<void> {
  await context.addCookies([
    { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
    { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
  ]);
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

async function createPublishedTieredTour(
  browser: Browser,
  partnerPage: Page,
  targetDate: string,
): Promise<{
  tourId: number;
  slug: string;
  optionId: number;
  departureId: number;
}> {
  await partnerPage.goto('/en/partner');
  const title = uniqueTitle('EGM005 Tiered E2E');

  const created = await apiPost(partnerPage, '/api/partner/tours', {
    title,
    description: LONG_DESCRIPTION,
    category: 'walking',
    destination: 'Rome, Italy',
    duration_value: 2,
    duration_unit: 'hour',
    difficulty_level: 'easy',
    guide_languages: ['en'],
    media: buildOrderedMedia(2, 'egm005-tier'),
    options: [
      {
        title: 'Tiered Morning Walk',
        pickup_instructions: 'Meet at Arch of Constantine',
        guide_languages: ['en'],
        price_amount: 8000,
        group_size_max: 6,
        start_time: '10:00',
        weekdays: [0, 1, 2, 3, 4, 5, 6],
      },
    ],
    translations: {
      en: {
        title,
        description: LONG_DESCRIPTION,
        highlights: ['Skip-the-line entry'],
        inclusions: ['Local guide'],
        exclusions: ['Meals'],
        important_information: ['Arrive 10 minutes early'],
        meeting_point: 'Arch of Constantine',
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

  expect(created.status).toBe(201);
  const tourId = (created.json as { data: { id: number; slug: string } }).data.id;
  const slug = (created.json as { data: { slug: string } }).data.slug;

  const tierRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/pricing`, {
    name: 'Base Standard',
    price: 80,
    min_participants: 1,
    max_participants: 6,
  });
  expect(tierRes.status).toBe(201);

  const ruleRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/availability/rules`, {
    rule_type: 'recurring',
    days_of_week: [0, 1, 2, 3, 4, 5, 6],
    start_time: '10:00',
    start_date: addDaysIso(1),
    end_date: addDaysIso(160),
    capacity: 6,
  });
  expect(ruleRes.status).toBe(201);

  const submitRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/submit`, {});
  expect(submitRes.status).toBe(200);

  await filamentPublishTour(browser, tourId);

  const detail = await apiGet(partnerPage, `/api/partner/tours/${tourId}`);
  expect(detail.status).toBe(200);
  const optionId = (detail.json as { data: { options: Array<{ id: number }> } }).data.options[0].id;

  const depRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/departures`, {
    tour_option_id: optionId,
    local_date: targetDate,
    local_time: '10:00',
    timezone: 'Europe/Rome',
    capacity: 6,
    booking_cutoff_hours: 2,
  });
  expect(depRes.status).toBe(201);
  const departureId = (depRes.json as { data: { id: number } }).data.id;

  return { tourId, slug, optionId, departureId };
}

test.describe('EGM-005 Wave 3D — Tiered Pricing, Explicit Departure Checkout & Price Drift Decline', () => {
  test.beforeEach(async ({ context }) => {
    await addConsentCookies(context);
  });

  test('configures per-person option tiers, quotes live public tiers, books cleanly, and handles real price drift decline + accept', async ({
    browser,
    page,
  }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    let dialogCount = 0;

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });
    page.on('pageerror', (err) => pageErrors.push(String(err)));
    page.on('dialog', async (dialog) => {
      dialogCount += 1;
      await dialog.dismiss();
    });

    const targetDate = addDaysIso(14);
    const { tourId, slug, optionId, departureId } = await createPublishedTieredTour(
      browser,
      page,
      targetDate,
    );

    const createdRefs: string[] = [];
    const travelerContext = await createIsolatedTravelerContext(browser, {
      viewport: page.viewportSize() ?? { width: 1280, height: 800 },
    });

    try {
      // 1) Localized partner pricing UI across EN / ES / IT + Axe check
      await page.goto(`/es/partner/tours/${tourId}/pricing`);
      await expect(page.getByRole('heading', { name: 'Gestión de precios del tour' })).toBeVisible();
      await page.goto(`/it/partner/tours/${tourId}/pricing`);
      await expect(page.getByRole('heading', { name: 'Gestione prezzi del tour' })).toBeVisible();

      await page.goto(`/en/partner/tours/${tourId}/pricing`);
      await expect(page.getByRole('heading', { name: 'Tour Pricing Management' })).toBeVisible();
      await expect(page.getByText('Pricing mode: Per person only')).toBeVisible();
      await expect(page.getByTestId('option-bracket-0')).toBeVisible();
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);

      // Configure 2 contiguous per-person brackets: 1-2 @ €80.00, 3-6 @ €60.00
      const bracket0 = page.getByTestId('option-bracket-0');
      await bracket0.getByLabel('Tier Name').fill('Solo/Duo');
      await bracket0.getByLabel('Price per person (EUR)').fill('80.00');
      await bracket0.getByLabel('Min Participants').fill('1');
      await bracket0.getByLabel('Max Participants (Optional)').fill('2');

      await page.getByRole('button', { name: 'Add bracket' }).click();
      const bracket1 = page.getByTestId('option-bracket-1');
      await bracket1.getByLabel('Tier Name').fill('Small Group');
      await bracket1.getByLabel('Price per person (EUR)').fill('60.00');
      await bracket1.getByLabel('Min Participants').fill('3');
      await bracket1.getByLabel('Max Participants (Optional)').fill('6');

      // Live preview reacts before saving
      const preview = page.getByRole('region', { name: 'Live Traveler Price Preview' });
      await expect(preview).toContainText('€80.00 / person');
      await expect(preview).toContainText('Total: €180.00');

      const syncRespPromise = page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith(`/api/partner/tours/${tourId}/pricing/sync`),
      );
      await page.getByRole('button', { name: 'Save tier schedule' }).click();
      const syncResp = await syncRespPromise;
      expect(syncResp.status()).toBe(200);
      await expect(page.getByRole('status')).toContainText('Option pricing tier schedule saved.');

      await page.reload();
      await expect(page.getByTestId('option-bracket-1').getByLabel('Tier Name')).toHaveValue('Small Group');

      // 2) Traveler views public tour detail: dynamic unit & total prices update by participant count
      const travelerPage = await travelerContext.newPage();
      await travelerPage.goto(`/en/tours/${slug}`);
      await expect(travelerPage.getByRole('heading', { level: 1 })).toBeVisible();

      const desktopSlot = travelerPage.getByTestId('tour-booking-card-slot');
      const isDesktopCardVisible = await desktopSlot.isVisible();

      if (isDesktopCardVisible) {
        await desktopSlot.getByRole('radio', { name: /Tiered Morning Walk/i }).click();
        await desktopSlot.locator('select').selectOption(targetDate);
        await expect(desktopSlot).toContainText('Group rate tiers');
        await expect(desktopSlot).toContainText('€80.00 / person');

        const incBtn = desktopSlot.getByRole('button', { name: /increase/i });
        await incBtn.click();
        await incBtn.click();
        await expect(desktopSlot).toContainText('€60.00 / person');
        await expect(desktopSlot).toContainText('€180.00');
      } else {
        const mobileBar = travelerPage.getByTestId('mobile-booking-bar');
        await mobileBar.getByRole('button', { name: 'Edit booking selections' }).click();
        const modal = travelerPage.getByRole('dialog');
        await expect(modal).toBeVisible();
        await modal.getByRole('radio', { name: /Tiered Morning Walk/i }).click();
        await modal.locator('select').selectOption(targetDate);
        await expect(modal).toContainText('Group rate tiers');

        const incBtn = modal.getByRole('button', { name: /increase/i });
        await incBtn.click();
        await incBtn.click();
        await expect(modal).toContainText('€180.00');
        await modal.getByRole('button', { name: /back|close/i }).first().click();
        await expect(mobileBar).toContainText('€60.00 / person');
      }

      // 3) Checkout: explicit departure selected, zero false drift, integer pricing snapshot persisted
      await travelerPage.goto(
        `/en/booking?tour=${slug}&option=${optionId}&participants=3&date=${targetDate}`,
      );
      const depFieldset = travelerPage.getByTestId('booking-departures');
      await expect(depFieldset).toBeVisible();
      await expect(depFieldset).toContainText('10:00 (Europe/Rome)');
      await expect(depFieldset).toContainText('6 spots left');
      await expect(depFieldset).toContainText('Small Group');
      await expect(travelerPage.getByText('€60.00 / person')).toBeVisible();
      await expect(travelerPage.getByText('€180.00').first()).toBeVisible();

      const createBookingPromise = travelerPage.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' && resp.url().endsWith('/api/public/bookings'),
      );
      await travelerPage.getByRole('button', { name: 'Confirm & Pay' }).click();
      const bookingResp = await createBookingPromise;
      expect(bookingResp.status()).toBe(201);
      const bookingJson = (await bookingResp.json()) as {
        price_changed: boolean;
        data: {
          reference: string;
          departure?: { id: number };
          pricing_snapshot?: { unit_price_cents: number; total_price_cents: number; tier_name: string };
        };
      };
      expect(bookingJson.price_changed).toBe(false);
      expect(bookingJson.data.departure?.id).toBe(departureId);
      expect(bookingJson.data.pricing_snapshot).toMatchObject({
        unit_price_cents: 6000,
        total_price_cents: 18000,
        tier_name: 'Small Group',
      });
      createdRefs.push(bookingJson.data.reference);

      await travelerPage.getByRole('button', { name: 'Complete test payment' }).click();
      await travelerPage.waitForURL(`**/booking/confirmation?ref=${bookingJson.data.reference}`);

      // Verify remaining spots dropped from 6 to 3
      const afterFirstDep = await travelerPage.request.get(
        `/api/public/tours/${slug}/departures?date_from=${targetDate}&date_to=${targetDate}&option_id=${optionId}&participants=1`,
      );
      expect(afterFirstDep.status()).toBe(200);
      const afterFirstJson = (await afterFirstDep.json()) as {
        data: Array<{ id: number; available_spots: number }>;
      };
      expect(afterFirstJson.data.find((d) => d.id === departureId)?.available_spots).toBe(3);

      // 4) Real price drift flow: load at €80.00 for 2 participants, partner raises bracket 1-2 to €95.00,
      //    submit -> modal displays €80.00 -> €95.00 -> Decline via POST /decline releases pending seats
      await travelerPage.goto(
        `/en/booking?tour=${slug}&option=${optionId}&participants=2&date=${targetDate}`,
      );
      await expect(travelerPage.getByTestId(`departure-radio-${departureId}`)).toBeChecked();
      await expect(travelerPage.getByText('€80.00 / person')).toBeVisible();

      const driftSync1 = await apiPost(page, `/api/partner/tours/${tourId}/pricing/sync`, {
        tour_option_id: optionId,
        pricing_mode: 'per_person',
        tiers: [
          { name: 'Solo/Duo', min_participants: 1, max_participants: 2, price_amount: 9500 },
          { name: 'Small Group', min_participants: 3, max_participants: 6, price_amount: 6000 },
        ],
      });
      expect(driftSync1.status).toBe(200);

      const driftCreate1 = travelerPage.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' && resp.url().endsWith('/api/public/bookings'),
      );
      await travelerPage.getByRole('button', { name: 'Confirm & Pay' }).click();
      const driftJson1 = (await (await driftCreate1).json()) as {
        price_changed: boolean;
        data: { reference: string };
      };
      expect(driftJson1.price_changed).toBe(true);
      createdRefs.push(driftJson1.data.reference);

      const driftDialog1 = travelerPage.getByRole('dialog');
      await expect(driftDialog1).toBeVisible();
      await expect(driftDialog1).toContainText('€80.00');
      await expect(driftDialog1).toContainText('€95.00');

      const declinePromise = travelerPage.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith(`/api/public/traveler/bookings/${driftJson1.data.reference}/decline`),
      );
      await driftDialog1.getByRole('button', { name: 'Cancel' }).click();
      const declineResp = await declinePromise;
      expect(declineResp.status()).toBe(200);
      await expect(driftDialog1).toBeHidden();

      // Pending seats released back to 3 and fresh quote now shows €95.00
      await expect(travelerPage.getByText('3 spots left').first()).toBeVisible();
      await expect(travelerPage.getByText('€95.00 / person')).toBeVisible();

      // Second drift: partner bumps €95.00 -> €105.00; traveler accepts & completes deterministic payment
      const driftSync2 = await apiPost(page, `/api/partner/tours/${tourId}/pricing/sync`, {
        tour_option_id: optionId,
        pricing_mode: 'per_person',
        tiers: [
          { name: 'Solo/Duo', min_participants: 1, max_participants: 2, price_amount: 10500 },
          { name: 'Small Group', min_participants: 3, max_participants: 6, price_amount: 6000 },
        ],
      });
      expect(driftSync2.status).toBe(200);

      const driftCreate2 = travelerPage.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' && resp.url().endsWith('/api/public/bookings'),
      );
      await travelerPage.getByRole('button', { name: 'Confirm & Pay' }).click();
      const driftJson2 = (await (await driftCreate2).json()) as {
        price_changed: boolean;
        data: { reference: string; pricing_snapshot?: { total_price_cents: number } };
      };
      expect(driftJson2.price_changed).toBe(true);
      expect(driftJson2.data.pricing_snapshot?.total_price_cents).toBe(21000);
      createdRefs.push(driftJson2.data.reference);

      const driftDialog2 = travelerPage.getByRole('dialog');
      await expect(driftDialog2).toContainText('€95.00');
      await expect(driftDialog2).toContainText('€105.00');
      await driftDialog2.getByRole('button', { name: 'Confirm at new price' }).click();

      await travelerPage.getByRole('button', { name: 'Complete test payment' }).click();
      await travelerPage.waitForURL(`**/booking/confirmation?ref=${driftJson2.data.reference}`);

      expect(dialogCount).toBe(0);
      expect(consoleErrors).toEqual([]);
      expect(pageErrors).toEqual([]);
    } finally {
      if (createdRefs.length > 0) {
        try {
          const cleanupPage = travelerContext.pages()[0] ?? (await travelerContext.newPage());
          await cleanupPage.goto('/en').catch(() => {});
          const tToken = await cleanupPage.evaluate(() => localStorage.getItem('auth_token')).catch(() => null);
          if (tToken) {
            for (const ref of createdRefs) {
              await cleanupPage.request
                .post(`/api/public/traveler/bookings/${encodeURIComponent(ref)}/cancel`, {
                  headers: {
                    Authorization: `Bearer ${tToken}`,
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                  },
                  data: { reason: 'E2E Wave 3D cleanup' },
                })
                .catch(() => {});
            }
          }
        } catch {
          // Best-effort cleanup when browser context was already torn down after timeout
        }
      }
      await travelerContext.close().catch(() => {});
      await apiPost(page, `/api/partner/tours/${tourId}/archive`, {}).catch(() => {});
    }
  });
});
