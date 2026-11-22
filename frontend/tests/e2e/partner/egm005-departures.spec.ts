import { randomUUID } from 'node:crypto';
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
 * EGM-005 Wave 3C — Explicit Departures, Independent Capacity & Recurrence Browser E2E (D3, D4).
 *
 * Drives the real Next.js partner frontend, Laravel API, Filament publish gate,
 * PostgreSQL persistence, and public/traveler APIs with zero mocked business logic.
 */
test.describe.configure({ mode: 'serial', timeout: 120000 });

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

async function createPublishedDepartureTestTour(browser: Browser, partnerPage: Page): Promise<{
  tourId: number;
  slug: string;
  optionId: number;
}> {
  await partnerPage.goto('/en/partner');
  const title = uniqueTitle('EGM005 Departures E2E');

  const created = await apiPost(partnerPage, '/api/partner/tours', {
    title,
    description: LONG_DESCRIPTION,
    category: 'walking',
    destination: 'Rome, Italy',
    duration_value: 2,
    duration_unit: 'hour',
    difficulty_level: 'easy',
    guide_languages: ['en'],
    media: buildOrderedMedia(2, 'egm005-dep'),
    options: [
      {
        title: 'Morning Departure Slot',
        pickup_instructions: 'Meet at Piazza del Colosseo',
        guide_languages: ['en'],
        price_amount: 5000,
        group_size_max: 10,
        start_time: '09:30',
        weekdays: [0, 1, 2, 3, 4, 5, 6],
      },
    ],
    translations: {
      en: {
        title,
        description: LONG_DESCRIPTION,
        highlights: ['Skip-the-line access'],
        inclusions: ['Licensed local guide'],
        exclusions: ['Food and drinks'],
        important_information: ['Arrive 15 minutes early'],
        meeting_point: 'Colosseum Metro Exit',
        cancellation_policy: 'Cancel up to 24 hours before departure for a full refund.',
        itinerary: [
          {
            day: 1,
            title: 'Colosseum Route',
            description: null,
            stops: [{ title: 'Arena overview', description: null, duration_minutes: 45 }],
          },
        ],
      },
    },
  });

  expect(created.status).toBe(201);
  const tourId = (created.json as { data: { id: number; slug: string } }).data.id;
  const slug = (created.json as { data: { slug: string } }).data.slug;

  const tierRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/pricing`, {
    name: 'Adult Standard',
    price: 50,
    min_participants: 1,
    max_participants: 10,
  });
  expect(tierRes.status).toBe(201);

  const submitRes = await apiPost(partnerPage, `/api/partner/tours/${tourId}/submit`, {});
  expect(submitRes.status).toBe(200);

  await filamentPublishTour(browser, tourId);

  const detail = await apiGet(partnerPage, `/api/partner/tours/${tourId}`);
  expect(detail.status).toBe(200);
  const data = (detail.json as { data: { status: string; options: Array<{ id: number }> } }).data;
  expect(data.status).toBe('published');
  expect(data.options.length).toBeGreaterThan(0);

  return { tourId, slug, optionId: data.options[0].id };
}

test.describe('EGM-005 Wave 3C — Partner Departures, D4 Capacity Floor & Public Projection', () => {
  test.beforeEach(async ({ context }) => {
    await addConsentCookies(context);
  });

  test('manages recurring rules, bounded departures, D4 Rule 10 guard, accessible cancellation, and EN/ES/IT a11y', async ({
    browser,
    context,
    page,
  }) => {
    const consoleErrors: string[] = [];
    const pageErrors: string[] = [];
    let allowedExpected422Logs = 0;
    let dialogCount = 0;

    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        const text = msg.text();
        if (allowedExpected422Logs > 0 && text.includes('status of 422')) {
          allowedExpected422Logs -= 1;
          return;
        }
        consoleErrors.push(text);
      }
    });
    page.on('pageerror', (err) => pageErrors.push(String(err)));
    page.on('dialog', async (dialog) => {
      dialogCount += 1;
      await dialog.dismiss();
    });

    const { tourId, slug, optionId } = await createPublishedDepartureTestTour(browser, page);
    let travelerBookingRef: string | null = null;

    const travelerContext = await createIsolatedTravelerContext(browser);

    try {
      // 1) Localized availability & departures UI (EN / ES / IT) + Axe accessibility scan
      await page.goto(`/en/partner/tours/${tourId}/availability`);
      await expect(page.getByRole('heading', { name: 'Tour Availability & Departures' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Explicit Departures & Independent Capacity' })).toBeVisible();
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);

      await page.goto(`/es/partner/tours/${tourId}/availability`);
      await expect(page.getByRole('heading', { name: 'Disponibilidad y salidas del tour' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Salidas explícitas y capacidad independiente' })).toBeVisible();

      await page.goto(`/it/partner/tours/${tourId}/availability`);
      await expect(page.getByRole('heading', { name: 'Disponibilità e partenze del tour' })).toBeVisible();
      await expect(page.getByRole('heading', { name: 'Partenze Esplicite e Capienza Indipendente' })).toBeVisible();

      // Return to EN for complete interactive workflow
      await page.goto(`/en/partner/tours/${tourId}/availability`);

      // 2) Add recurring rule (verifies rule_type='recurring' fix) and generate bounded departures
      const ruleStart = addDaysIso(90);
      const ruleEnd = addDaysIso(93);
      await page.getByRole('button', { name: 'Mon', exact: true }).click();
      await page.getByRole('button', { name: 'Tue', exact: true }).click();
      await page.getByRole('button', { name: 'Wed', exact: true }).click();
      await page.getByRole('button', { name: 'Thu', exact: true }).click();
      await page.getByRole('button', { name: 'Fri', exact: true }).click();
      await page.getByRole('button', { name: 'Sat', exact: true }).click();
      await page.getByRole('button', { name: 'Sun', exact: true }).click();
      await page.locator('#rule-start-time').fill('09:30');
      await page.locator('#rule-max-capacity').fill('10');
      await page.locator('#rule-start-date').fill(ruleStart);
      await page.locator('#rule-end-date').fill(ruleEnd);

      const createRuleResp = page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith(`/api/partner/tours/${tourId}/availability/rules`),
      );
      await page.getByRole('button', { name: 'Save Schedule Rule' }).click();
      expect((await createRuleResp).status()).toBe(201);
      await expect(page.getByText('Availability rule saved.')).toBeVisible();

      // Generate departures for [ruleStart, ruleEnd]
      await page.locator('#gen-option').selectOption(String(optionId));
      await page.locator('#gen-timezone').selectOption('Europe/Rome');
      await page.locator('#gen-start-date').fill(ruleStart);
      await page.locator('#gen-end-date').fill(ruleEnd);

      const genRespPromise = page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith(`/api/partner/tours/${tourId}/departures/generate`),
      );
      await page.getByTestId('generate-departures-submit').click();
      const genResp = await genRespPromise;
      expect(genResp.status()).toBe(200);
      const genBody = (await genResp.json()) as { data: Array<{ id: number }>; meta: { generated_count: number } };
      expect(genBody.meta.generated_count).toBeGreaterThanOrEqual(4);

      const unbookedGeneratedId = genBody.data[0].id;
      await expect(page.getByTestId(`departure-row-${unbookedGeneratedId}`)).toBeVisible();

      // 3) Create single explicit departure on isolated future date + verify persistence across reload
      const singleDate = addDaysIso(125);
      await page.locator('#dep-create-option').selectOption(String(optionId));
      await page.locator('#dep-create-tz').selectOption('America/New_York');
      await page.locator('#dep-create-date').fill(singleDate);
      await page.locator('#dep-create-time').fill('14:30');
      await page.locator('#dep-create-capacity').fill('6');
      await page.locator('#dep-create-cutoff').fill('4');

      const createDepPromise = page.waitForResponse(
        (resp) =>
          resp.request().method() === 'POST' &&
          resp.url().endsWith(`/api/partner/tours/${tourId}/departures`),
      );
      await page.getByTestId('create-departure-submit').click();
      const createDepResp = await createDepPromise;
      expect(createDepResp.status()).toBe(201);
      const createdDep = ((await createDepResp.json()) as { data: { id: number } }).data;
      const singleDepId = createdDep.id;

      await page.reload();
      const singleRow = page.getByTestId(`departure-row-${singleDepId}`);
      await expect(singleRow).toBeVisible();
      await expect(singleRow).toContainText(`${singleDate} · 14:30`);
      await expect(singleRow).toContainText('America/New_York');
      await expect(singleRow).toContainText('0 / 6 reserved');
      await expect(singleRow).toContainText('6 spots left');

      // Public departures API reflects the newly created explicit departure
      const publicBeforeBooking = await page.request.get(
        `/api/public/tours/${slug}/departures?date_from=${singleDate}&date_to=${singleDate}&participants=1`,
      );
      expect(publicBeforeBooking.status()).toBe(200);
      const pubBeforeJson = (await publicBeforeBooking.json()) as {
        data: Array<{ id: number; available_spots: number }>;
      };
      expect(pubBeforeJson.data.find((d) => d.id === singleDepId)?.available_spots).toBe(6);

      // 4) Cancel the unbooked generated departure via inline UI confirmation (no window.confirm)
      const unbookedRow = page.getByTestId(`departure-row-${unbookedGeneratedId}`);
      await unbookedRow.getByRole('button', { name: 'Cancel departure' }).click();
      await expect(unbookedRow.getByText('Cancel this departure?')).toBeVisible();
      await unbookedRow.getByRole('button', { name: 'Confirm cancellation' }).click();
      await expect(unbookedRow).toContainText('Cancelled');
      expect(dialogCount).toBe(0);

      // Delete the schedule rule using inline confirmation (no window.confirm)
      await page.getByRole('button', { name: 'Remove rule' }).first().click();
      await page.getByRole('button', { name: 'Confirm delete' }).click();
      await expect(page.getByText('Availability rule deleted.')).toBeVisible();
      expect(dialogCount).toBe(0);

      // 5) Real traveler creates & confirms a 3-seat booking on singleDepId
      await addConsentCookies(travelerContext);
      const travelerPage = await travelerContext.newPage();
      await travelerPage.goto('/en');
      const travelerToken = await travelerPage.evaluate(() => localStorage.getItem('auth_token'));
      expect(travelerToken).toBeTruthy();

      const bookResp = await travelerPage.request.post('/api/public/bookings', {
        headers: {
          Authorization: `Bearer ${travelerToken}`,
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': randomUUID(),
        },
        data: {
          tour_slug: slug,
          tour_date: singleDate,
          participant_count: 3,
          locale: 'en',
          page_load_price: 5000,
          tour_option_id: optionId,
          tour_departure_id: singleDepId,
        },
      });
      expect(bookResp.status()).toBe(201);
      const bookJson = (await bookResp.json()) as {
        data: { reference: string };
        payment?: { client_secret?: string };
      };
      travelerBookingRef = bookJson.data.reference;
      const clientSecret = bookJson.payment?.client_secret;
      expect(travelerBookingRef).toBeTruthy();
      expect(clientSecret).toBeTruthy();

      const confirmPayResp = await travelerPage.request.post(
        `/api/public/bookings/${encodeURIComponent(travelerBookingRef)}/deterministic-payment/confirm`,
        {
          headers: {
            Authorization: `Bearer ${travelerToken}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          data: { client_secret: clientSecret },
        },
      );
      expect(confirmPayResp.status()).toBe(200);

      // 6) Partner reloads Availability page -> D4 Rule 10 reserved-seat floor + valid capacity increase
      await page.reload();
      await expect(singleRow).toContainText('3 / 6 reserved');
      await expect(singleRow).toContainText('3 spots left');

      await singleRow.getByRole('button', { name: 'Edit capacity' }).click();
      await page.locator(`#edit-cap-${singleDepId}`).fill('2');
      allowedExpected422Logs += 1;
      const rule10RespPromise = page.waitForResponse(
        (resp) =>
          resp.request().method() === 'PUT' &&
          resp.url().endsWith(`/api/partner/tours/${tourId}/departures/${singleDepId}`),
      );
      await singleRow.getByRole('button', { name: 'Save changes' }).click();
      expect((await rule10RespPromise).status()).toBe(422);

      await expect(singleRow.getByRole('alert')).toContainText(
        'Cannot reduce departure capacity below currently reserved seats (3).',
      );

      await page.locator(`#edit-cap-${singleDepId}`).fill('8');
      await page.locator(`#edit-cutoff-${singleDepId}`).fill('12');
      await singleRow.getByRole('button', { name: 'Save changes' }).click();
      await expect(singleRow).toContainText('3 / 8 reserved');
      await expect(singleRow).toContainText('5 spots left');
      await expect(singleRow).toContainText('12h cutoff');

      // Public API immediately reflects capacity increase & remaining spots after cache invalidation
      const publicAfterEdit = await page.request.get(
        `/api/public/tours/${slug}/departures?date_from=${singleDate}&date_to=${singleDate}&participants=1`,
      );
      expect(publicAfterEdit.status()).toBe(200);
      const pubAfterJson = (await publicAfterEdit.json()) as {
        data: Array<{ id: number; available_spots: number }>;
      };
      expect(pubAfterJson.data.find((d) => d.id === singleDepId)?.available_spots).toBe(5);

      // 7) Partner cancels booked departure: warning & checkbox gate required, public hides it, booking stays intact
      await singleRow.getByRole('button', { name: 'Cancel departure' }).click();
      const cancelRegion = singleRow.getByRole('region', { name: 'Cancel this departure?' });
      await expect(cancelRegion).toContainText('3 seats are already reserved');
      const confirmCancelBtn = cancelRegion.getByRole('button', { name: 'Confirm cancellation' });
      await expect(confirmCancelBtn).toBeDisabled();

      await cancelRegion.getByRole('checkbox').check();
      await expect(confirmCancelBtn).toBeEnabled();
      await confirmCancelBtn.click();

      await expect(singleRow).toContainText('Cancelled');
      expect(dialogCount).toBe(0);

      // Public departures API no longer exposes cancelled departure
      const publicAfterCancel = await page.request.get(
        `/api/public/tours/${slug}/departures?date_from=${singleDate}&date_to=${singleDate}&participants=1`,
      );
      expect(publicAfterCancel.status()).toBe(200);
      const pubCancelJson = (await publicAfterCancel.json()) as { data: Array<{ id: number }> };
      expect(pubCancelJson.data.some((d) => d.id === singleDepId)).toBe(false);

      // Traveler booking remains intact after departure cancellation
      const travelerBookingRes = await travelerPage.request.get(
        `/api/public/traveler/bookings/${encodeURIComponent(travelerBookingRef)}`,
        {
          headers: {
            Authorization: `Bearer ${travelerToken}`,
            Accept: 'application/json',
          },
        },
      );
      expect(travelerBookingRes.status()).toBe(200);

      // Final Axe sweep and runtime health checks
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([]);
      expect(consoleErrors).toEqual([]);
      expect(pageErrors).toEqual([]);
    } finally {
      if (travelerBookingRef) {
        const travelerPage = travelerContext.pages()[0] ?? (await travelerContext.newPage());
        await travelerPage.goto('/en').catch(() => {});
        const tToken = await travelerPage.evaluate(() => localStorage.getItem('auth_token')).catch(() => null);
        if (tToken) {
          await travelerPage.request
            .post(`/api/public/traveler/bookings/${encodeURIComponent(travelerBookingRef)}/cancel`, {
              headers: {
                Authorization: `Bearer ${tToken}`,
                Accept: 'application/json',
                'Content-Type': 'application/json',
              },
              data: { reason: 'E2E Wave 3C fixture cleanup' },
            })
            .catch(() => {});
        }
      }
      await travelerContext.close().catch(() => {});
      await apiPost(page, `/api/partner/tours/${tourId}/archive`, {}).catch(() => {});
      void context;
    }
  });
});
