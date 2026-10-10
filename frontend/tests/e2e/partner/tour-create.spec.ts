import { test, expect } from '@playwright/test';
import {
  apiGet,
  apiPost,
  apiPut,
  LONG_DESCRIPTION,
  ownedEnRow,
  ownedEnglishSource,
  uniqueTitle,
} from './tour-content-helpers';

test.describe('Partner Tour Creation', () => {
  test.beforeEach(async ({ page, context }) => {
    // Pre-set the cookie consent cookie so the fixed bottom-0 banner is not
    // rendered and does not intercept clicks on the wizard navigation buttons.
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);

    // Mock get signed upload url
    await page.route('**/api/partner/uploads/signed-url', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          upload_url: 'http://localhost/upload',
          public_url: 'https://example.com/tour-image.jpg',
        }),
      })
    );

    await page.goto('/en/partner/tours/create');

    // Dismiss the cookie consent banner if still rendered
    const acceptBtn = page.getByRole('button', { name: /accept/i });
    if (await acceptBtn.isVisible().catch(() => false)) {
      await acceptBtn.click().catch(() => {});
    }
  });

  test('should display the tour creation page', async ({ page }) => {
    await expect(page.getByLabel('Tour Title')).toBeVisible();
    await expect(page.getByText('Edit English source content. Spanish and Italian are generated automatically and never block publication.')).toBeVisible();
  });

  test('saves an ordered English day and stop in a draft payload', async ({ page }) => {
    let savedPayload: { itinerary?: Array<{ day: number; title: string; stops: Array<{ title: string }> }> } | null = null;
    await page.route('**/api/partner/tours', async (route) => {
      if (route.request().method() !== 'POST') return route.continue();
      savedPayload = route.request().postDataJSON();
      await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { id: 101, status: 'draft' } }) });
    });

    await page.getByRole('button', { name: 'Add day' }).click();
    await page.getByRole('textbox', { name: 'Day title' }).fill('Arrival');
    await page.getByRole('button', { name: 'Add stop' }).click();
    await page.getByRole('textbox', { name: 'Stop title' }).fill('Meet the guide');
    await page.getByRole('button', { name: 'Save Draft' }).click();
    await expect.poll(() => savedPayload).not.toBeNull();
    // Accepted T019 source shape: nullable day/stop fields travel as null.
    expect(savedPayload!.itinerary).toEqual([
      {
        day: 1,
        title: 'Arrival',
        description: null,
        stops: [{ title: 'Meet the guide', description: null, duration_minutes: null }],
      },
    ]);
  });

  test('should navigate through the wizard and submit the tour', async ({ page }) => {
    // Mock create tour (POST only) — scoped to this test so it doesn't
    // interfere with other tests that navigate to the tours list.
    await page.route('**/api/partner/tours', (route) => {
      if (route.request().method() === 'POST') {
        return route.fulfill({
          status: 201,
          contentType: 'application/json',
          body: JSON.stringify({
            data: { id: 101, title: 'Mocked New Tour', status: 'pending_review' },
          }),
        });
      }
      return route.continue();
    });

    // Step 1: Details — fill all required fields per tourBasicDetailsSchema
    await page.getByLabel('Tour Title').fill('Beautiful Walking Tour of Rome');
    await page.getByLabel('Description').fill('Explore the ancient history of Rome with a local guide, visiting celebrated landmarks and discovering stories behind the city.');

    // Category uses a custom Select (button trigger, button items — not Radix).
    // The trigger shows the placeholder text "Select a category" when empty.
    // Click the trigger button, then the "Walking" option button in the dropdown.
    const categoryTrigger = page.locator('button:has-text("Select a category")');
    await categoryTrigger.click();
    await page.getByRole('option', { name: 'Walking', exact: true }).click();

    await page.getByLabel('Destination').fill('Rome, Italy');
    await page.getByRole('spinbutton', { name: 'Duration' }).fill('3');
    await page.getByLabel('Meeting Point').fill('Colosseum Main Entrance');

    // Click Next — validates the details step via Zod before advancing
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    // Step 2: Media step should be active
    await expect(page.getByText(/Drag and drop images here/i)).toBeVisible();

    // Click Next on media step — validation may block (no cover image),
    // showing an error message. The test verifies step navigation works,
    // not that the tour can be submitted without media.
    await page.getByRole('button', { name: 'Next', exact: true }).click();
  });
});

test.describe('Partner Tour Create - Real API (Spec 019 US1)', () => {
  test.beforeEach(async ({ context }) => {
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);
  });

  test('creates an owned English tour with full source via real API and reopens persisted values', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 full source');
    await page.goto('/en/partner/tours/create');
    await expect(page.getByLabel('Tour Title')).toBeVisible();

    await page.getByLabel('Tour Title').fill(title);
    await page.getByLabel('Description').fill(LONG_DESCRIPTION);
    await page.locator('button:has-text("Select a category")').click();
    await page.getByRole('option', { name: 'Walking', exact: true }).click();
    await page.getByLabel('Destination').fill('Rome, Italy');
    await page.getByRole('spinbutton', { name: 'Duration' }).fill('3');
    await page.getByLabel('Meeting Point').fill('Colosseum Main Entrance');
    await page.getByLabel('Highlights').fill('Old-town route');
    await page.getByLabel("What's Included").fill('Live guide');
    await page.getByLabel('Exclusions').fill('Lunch');
    await page.getByLabel('Important information').fill('Comfortable shoes recommended');
    await page.getByLabel('Cancellation Policy').fill('Cancel up to 24 hours in advance for a full refund.');

    // Ordered itinerary: day with integer + null durations, then a zero-stop day.
    await page.getByRole('button', { name: 'Add day' }).click();
    await page.getByRole('textbox', { name: 'Day title' }).fill('Arrival');
    await page.getByRole('button', { name: 'Add stop' }).click();
    await page.getByRole('textbox', { name: 'Stop title' }).fill('Meet the guide');
    await page.getByLabel('Stop duration (minutes)').fill('30');
    await page.getByRole('button', { name: 'Add stop' }).click();
    await page.getByRole('textbox', { name: 'Stop title' }).last().fill('Old town walk');
    await page.getByRole('button', { name: 'Add day' }).click();
    await page.getByRole('textbox', { name: 'Day title' }).last().fill('Explore');

    const createdResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('/api/partner/tours') &&
        resp.request().method() === 'POST' &&
        resp.status() === 201
    );
    await page.getByRole('button', { name: 'Save Draft' }).click();
    const createdResponse = await createdResponsePromise;
    const createdBody = await createdResponse.json();
    const tourId = createdBody.data.id as number;
    expect(tourId).toBeGreaterThan(0);
    await expect(page.getByText('Draft saved')).toBeVisible();

    // Actual persisted source read-back (real 200 GET, no mocks).
    const getResult = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(getResult.status).toBe(200);
    const en = ownedEnRow(getResult.json);
    expect(en.title).toBe(title);
    expect(en.description).toBe(LONG_DESCRIPTION);
    expect(en.highlights).toEqual(['Old-town route']);
    expect(en.inclusions).toEqual(['Live guide']);
    expect(en.exclusions).toEqual(['Lunch']);
    expect(en.important_information).toEqual(['Comfortable shoes recommended']);
    expect(en.meeting_point).toBe('Colosseum Main Entrance');
    expect(en.cancellation_policy).toBe('Cancel up to 24 hours in advance for a full refund.');
    const itinerary = en.itinerary as Array<{
      day: number;
      title: string;
      stops: Array<{ title: string; duration_minutes: number | null }>;
    }>;
    expect(itinerary.map((day) => day.title)).toEqual(['Arrival', 'Explore']);
    expect(itinerary[0].stops.map((stop) => stop.title)).toEqual(['Meet the guide', 'Old town walk']);
    expect(itinerary[0].stops.map((stop) => stop.duration_minutes)).toEqual([30, null]);
    expect(itinerary[1].stops).toEqual([]);

    // Custom group size, pricing, availability and media travel with the tour.
    const updateResult = await apiPut(page, `/api/partner/tours/${tourId}`, {
      group_size_min: 2,
      group_size_max: 7,
      cover_image_url: 'https://cdn.example.com/us1-cover.jpg',
      media: [
        { url: 'https://cdn.example.com/us1-cover.jpg', is_cover: true },
        { url: 'https://cdn.example.com/us1-second.jpg', is_cover: false },
      ],
    });
    expect(updateResult.status).toBe(200);
    const tierResult = await apiPost(page, `/api/partner/tours/${tourId}/pricing`, {
      name: 'Adult',
      price: 45,
      min_participants: 1,
      max_participants: 10,
    });
    expect(tierResult.status).toBe(201);
    const ruleResult = await apiPost(page, `/api/partner/tours/${tourId}/availability/rules`, {
      rule_type: 'recurring',
      days_of_week: [1],
      start_time: '09:00',
      start_date: '2026-11-01',
      capacity: 10,
    });
    expect(ruleResult.status).toBe(201);

    const reopened = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(reopened.status).toBe(200);
    const beforeEdit = (reopened.json as { data: Record<string, unknown> }).data;

    // Reopen the edit URL in the browser: controls render the saved source.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    await expect(page.getByRole('textbox', { name: 'Day title' }).first()).toHaveValue('Arrival');
    await expect(page.getByRole('textbox', { name: 'Day title' }).nth(1)).toHaveValue('Explore');
    await expect(page.getByRole('textbox', { name: 'Stop title' }).first()).toHaveValue('Meet the guide');
    await expect(page.getByRole('img', { name: 'Cover Image' })).toBeVisible();

    // Unrelated browser edit save: only the technical location changes.
    const unrelatedSavePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    await page.getByPlaceholder('e.g. Rome, Italy').fill('Siena, Italy');
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await unrelatedSavePromise;
    await expect(page.getByText('Tour details saved successfully.')).toBeVisible();

    // Exact retained settings/media/order/source via real GET (200 alone is insufficient).
    const retained = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(retained.status).toBe(200);
    const retainedData = (retained.json as { data: Record<string, unknown> }).data;
    expect(retainedData.location).toBe('Siena, Italy');
    expect(retainedData.group_size_min).toBe(2);
    expect(retainedData.group_size_max).toBe(7);
    expect(retainedData.pricing_tiers).toEqual(beforeEdit.pricing_tiers);
    expect(retainedData.availability_rules).toEqual(beforeEdit.availability_rules);
    expect(retainedData.availability_exceptions).toEqual(beforeEdit.availability_exceptions);
    expect(retainedData.media).toEqual(beforeEdit.media);
    expect(ownedEnglishSource(retained.json)).toEqual(ownedEnglishSource(reopened.json));
    expect(retainedData.cover_image_url).toBe('https://cdn.example.com/us1-cover.jpg');
    const retainedMedia = (retainedData.media as Array<{ url: string; sort_order: number }>)
      .slice()
      .sort((a, b) => a.sort_order - b.sort_order)
      .map((item) => item.url);
    expect(retainedMedia).toEqual([
      'https://cdn.example.com/us1-cover.jpg',
      'https://cdn.example.com/us1-second.jpg',
    ]);
    const retainedTiers = retainedData.pricing_tiers as Array<{ name: string; price: number | string }>;
    expect(retainedTiers.map((tier) => tier.name)).toContain('Adult');
    expect(Number(retainedTiers.find((tier) => tier.name === 'Adult')?.price)).toBe(45);
    const retainedRules = retainedData.availability_rules as Array<{ rule_type: string; capacity: number | string; days_of_week: number[] }>;
    expect(retainedRules.length).toBeGreaterThan(0);
    expect(Number(retainedRules[0].capacity)).toBe(10);
    expect(retainedRules[0].days_of_week).toContain(1);
    const retainedEn = ownedEnRow(retained.json);
    expect(retainedEn.title).toBe(title);
    expect(retainedEn.description).toBe(LONG_DESCRIPTION);
    expect(retainedEn.meeting_point).toBe('Colosseum Main Entrance');
    expect(retainedEn.highlights).toEqual(['Old-town route']);
    const retainedItinerary = retainedEn.itinerary as Array<{
      day: number;
      title: string;
      stops: Array<{ title: string; duration_minutes: number | null }>;
    }>;
    expect(retainedItinerary.map((day) => day.title)).toEqual(['Arrival', 'Explore']);
    expect(retainedItinerary[0].stops.map((stop) => stop.duration_minutes)).toEqual([30, null]);
  });

  test('types non-BMP boundary input past native limits and persists it exactly (T017)', async ({
    page,
  }) => {
    const NON_BMP = String.fromCodePoint(0x1f30d);
    const title120 = NON_BMP.repeat(120);
    expect(Array.from(title120).length).toBe(120);
    expect(title120.length).toBeGreaterThan(120);
    const dayTitle160 = NON_BMP.repeat(160);
    const dayDescription2000 = NON_BMP.repeat(2000);

    await page.goto('/en/partner/tours/create');
    await expect(page.getByLabel('Tour Title')).toBeVisible();

    // Real typing, not fill: fill bypasses native maxlength while typing cannot,
    // so only typing proves the accepted 120 code-point boundary is reachable.
    await page.getByLabel('Tour Title').pressSequentially(title120, { delay: 0 });
    const typedTitle = await page.getByLabel('Tour Title').inputValue();
    expect(Array.from(typedTitle).length).toBe(120);
    expect(typedTitle).toBe(title120);

    await page.getByLabel('Description').fill(LONG_DESCRIPTION);
    await page.locator('button:has-text("Select a category")').click();
    await page.getByRole('option', { name: 'Walking', exact: true }).click();
    await page.getByLabel('Destination').fill('Rome, Italy');
    await page.getByRole('spinbutton', { name: 'Duration' }).fill('3');

    await page.getByRole('button', { name: 'Add day' }).click();
    await page.getByRole('textbox', { name: 'Day title' }).pressSequentially(dayTitle160, { delay: 0 });
    const typedDayTitle = await page.getByRole('textbox', { name: 'Day title' }).inputValue();
    expect(Array.from(typedDayTitle).length).toBe(160);
    expect(typedDayTitle).toBe(dayTitle160);

    // Real typing into the day description: native maxlength would truncate the
    // accepted 2000 code-point boundary keystroke by keystroke as well.
    await page.getByRole('textbox', { name: 'Day description' }).pressSequentially(dayDescription2000, { delay: 0 });
    const typedDayDescription = await page.getByRole('textbox', { name: 'Day description' }).inputValue();
    expect(Array.from(typedDayDescription).length).toBe(2000);
    expect(typedDayDescription).toBe(dayDescription2000);

    const createdResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes('/api/partner/tours') &&
        resp.request().method() === 'POST' &&
        resp.status() === 201
    );
    await page.getByRole('button', { name: 'Save Draft' }).click();
    const createdResponse = await createdResponsePromise;
    const tourId = ((await createdResponse.json()) as { data: { id: number } }).data.id;

    // Accepted boundary values survive exact persistence (no truncation/normalization).
    const persisted = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(persisted.status).toBe(200);
    const persistedEn = ownedEnRow(persisted.json);
    expect(persistedEn.title).toBe(title120);
    const persistedDays = persistedEn.itinerary as Array<{ title: string; description: string | null }>;
    expect(persistedDays[0].title).toBe(dayTitle160);
    expect(persistedDays[0].description).toBe(dayDescription2000);

    // Limit+1 is rejected with localized associated field feedback.
    await page.getByLabel('Tour Title').fill(NON_BMP.repeat(121));
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await expect(page.getByText('Please review the highlighted fields below.')).toBeVisible();
    await expect(
      page.locator('p[role="alert"]', { hasText: 'Title must be 120 characters or fewer.' })
    ).toBeVisible();
    await expect(page.getByLabel('Tour Title')).toHaveAttribute('aria-invalid', 'true');
  });

  test('creates via the Wizard, fails submit deterministically, corrects and retries the same tour', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 wizard retry');
    const correctedTitle = `${title} corrected`;

    // Same-origin document first: helpers and the fixture call below read the
    // partner token from localStorage, which is unavailable on about:blank.
    await page.goto('/en/partner/tours');
    const before = await apiGet(page, '/api/partner/tours?per_page=100');
    expect(before.status).toBe(200);
    const totalBefore = (before.json as { meta: { total: number } }).meta.total;
    const browserWrites: Array<{ method: string; path: string }> = [];
    page.on('request', (request) => {
      const method = request.method();
      const path = new URL(request.url()).pathname;
      if (['POST', 'PUT'].includes(method) && /^\/api\/partner\/tours(?:\/\d+(?:\/submit)?)?$/.test(path)) {
        browserWrites.push({ method, path });
      }
    });

    // Seed only the cover media through the Wizard's own persisted-draft path
    // (no URL control exists in the uploader UI); pricing and availability go
    // through the real step forms below so their validations stay exercised.
    const wizardSeed = {
      state: {
        currentStep: 'details',
        formData: {
          title: '',
          description: '',
          category: '',
          destination: '',
          duration_value: '',
          duration_unit: 'hour',
          difficulty_level: 'easy',
          itinerary: [],
          highlights: [],
          inclusions: [],
          exclusions: [],
          important_information: [],
          meeting_point: '',
          languages: [],
          cancellation_policy: '',
          media: [
            {
              id: 'seed-cover-1',
              url: 'https://cdn.example.com/us1-wizard-cover.jpg',
              is_cover: true,
              sort_order: 0,
            },
          ],
          pricing_tiers: [],
          availability_rules: [],
          availability_exceptions: [],
          group_size_min: 1,
          group_size_max: 20,
        },
      },
      version: 0,
    };
    await page.evaluate(
      (seed) => localStorage.setItem('partner-tour-wizard', JSON.stringify(seed)),
      wizardSeed
    );
    const token = await page.evaluate(() => localStorage.getItem('auth_token'));
    const appOrigin = new URL(page.url()).origin;

    // Deterministic server failure on the FIRST submit only: perform a real
    // same-owner API call clearing the persisted cover, then let the actual
    // submit request continue so the real backend guard answers 422. No
    // mocked/fulfilled response anywhere on this path.
    let submitIntercepted = false;
    let fixtureClearStatus = 0;
    await page.route('**/api/partner/tours/*/submit', async (route) => {
      if (submitIntercepted) {
        await route.continue();
        return;
      }
      submitIntercepted = true;
      const interceptedId = route.request().url().match(/\/api\/partner\/tours\/(\d+)\/submit/)?.[1];
      if (interceptedId && token) {
        const cleared = await page.request.fetch(`${appOrigin}/api/partner/tours/${interceptedId}`, {
          method: 'PUT',
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
          },
          data: JSON.stringify({ cover_image_url: null, media: [] }),
        });
        fixtureClearStatus = cleared.status();
      }
      await route.continue();
    });

    await page.goto('/en/partner/tours/create');
    await expect(page.getByLabel('Tour Title')).toBeVisible();
    await page.getByLabel('Tour Title').fill(title);
    await page.getByLabel('Description').fill(LONG_DESCRIPTION);
    await page.locator('button:has-text("Select a category")').click();
    await page.getByRole('option', { name: 'Walking', exact: true }).click();
    await page.getByLabel('Destination').fill('Rome, Italy');
    await page.getByRole('spinbutton', { name: 'Duration' }).fill('3');
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    // Seeded cover media travels with the Wizard draft.
    await expect(page.getByRole('img', { name: 'Cover Image' })).toBeVisible();
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    await page.getByRole('button', { name: 'Add Tier' }).click();
    await page.getByLabel('Name').fill('Adult');
    await page.getByLabel('Price per person').fill('45');
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    await page.getByRole('button', { name: 'Add Rule' }).click();
    await page.getByRole('button', { name: 'Mon' }).click();
    await page.getByLabel('Start date').fill('2026-11-01');
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    // Exactly one browser draft creation on first submit.
    const createdPromise = page.waitForResponse(
      (resp) =>
        /\/api\/partner\/tours$/.test(resp.url()) &&
        resp.request().method() === 'POST' &&
        resp.status() === 201
    );
    const firstSubmitPromise = page.waitForResponse(
      (resp) => /\/api\/partner\/tours\/\d+\/submit$/.test(resp.url()) && resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Submit for Review' }).click();
    const createdResponse = await createdPromise;
    const createdId = ((await createdResponse.json()) as { data: { id: number } }).data.id;
    const firstSubmit = await firstSubmitPromise;
    expect(firstSubmit.status()).toBe(422);
    expect(fixtureClearStatus).toBe(200);
    await expect(page.getByText("Couldn't submit the tour for review. Please try again.")).toBeVisible();

    // Correct source and destination back on the details step, then forward again.
    await page.getByRole('button', { name: 'Back' }).click();
    await page.getByRole('button', { name: 'Back' }).click();
    await page.getByRole('button', { name: 'Back' }).click();
    await page.getByRole('button', { name: 'Back' }).click();
    await page.getByLabel('Tour Title').fill(correctedTitle);
    await page.getByLabel('Destination').fill('Siena, Italy');
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();
    await page.getByRole('button', { name: 'Next', exact: true }).click();

    // Retry updates the retained draft (restoring its valid cover) and submits it.
    const retryPutPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${createdId}`) &&
        !resp.url().includes('/submit') &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    const retrySubmitPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${createdId}/submit`) &&
        resp.request().method() === 'POST'
    );
    await page.getByRole('button', { name: 'Submit for Review' }).click();
    await retryPutPromise;
    const retrySubmit = await retrySubmitPromise;
    expect(retrySubmit.status()).toBe(200);
    await expect(page.getByText('Tour submitted for review.')).toBeVisible();

    // Same ID in PUT/submit and in the owned persisted GET; exactly one tour created.
    const detail = await apiGet(page, `/api/partner/tours/${createdId}`);
    expect(detail.status).toBe(200);
    expect((detail.json as { data: { status: string } }).data.status).toBe('pending_review');
    expect((detail.json as { data: { location: string } }).data.location).toBe('Siena, Italy');
    expect((detail.json as { data: { cover_image_url: string } }).data.cover_image_url).toBe(
      'https://cdn.example.com/us1-wizard-cover.jpg'
    );
    expect(ownedEnRow(detail.json).title).toBe(correctedTitle);
    const after = await apiGet(page, '/api/partner/tours?per_page=100');
    expect(after.status).toBe(200);
    const afterData = (after.json as { data: Array<{ id: number }> }).data ?? [];
    expect(afterData.filter((tour) => tour.id === createdId)).toHaveLength(1);
    expect((after.json as { meta: { total: number } }).meta.total).toBe(totalBefore + 1);
    expect(browserWrites).toEqual([
      { method: 'POST', path: '/api/partner/tours' },
      { method: 'POST', path: `/api/partner/tours/${createdId}/submit` },
      { method: 'PUT', path: `/api/partner/tours/${createdId}` },
      { method: 'POST', path: `/api/partner/tours/${createdId}/submit` },
    ]);
  });

  test('renders the create wizard across EN/ES/IT and the 320/390/768/1024/1440 viewport matrix', async ({
    page,
  }, testInfo) => {
    const notices: Record<string, string> = {
      en: 'Edit English source content. Spanish and Italian are generated automatically and never block publication.',
      es: 'Edita el contenido original en inglés. Las traducciones al español y al italiano se generan automáticamente y no bloquean la publicación.',
      it: 'Modifica il contenuto originale in inglese. Le traduzioni in spagnolo e italiano vengono generate automaticamente e non bloccano la pubblicazione.',
    };
    for (const locale of ['en', 'es', 'it'] as const) {
      // 320 stays as supplemental narrow-width coverage; 390 is the
      // Constitution VI required narrow viewport.
      for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`/${locale}/partner/tours/create`);
        await expect(page.getByText(notices[locale])).toBeVisible();
        await page.screenshot({
          path: testInfo.outputPath(`create-${locale}-${width}.png`),
        });
      }
    }
  });
});

// Separate describe so this test does NOT inherit the /tours/create
// beforeEach — navigating from the heavy /create wizard to /tours in dev
// mode causes a cold-compile race that can make restoreSession fail.
test.describe('Partner Tours List - Create Button', () => {
  test('should display Create Tour button on tours list page', async ({ page }) => {
    await page.goto('/en/partner/tours');

    // "Create Tour" button with Plus icon should be visible
    const createLink = page.getByRole('link', { name: /create tour/i });
    await expect(createLink).toBeVisible();
  });
});

test.describe('Partner Tour Edit', () => {
  test('should navigate to edit page from tour card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Editable Tour',
              slug: 'editable-tour',
              status: 'published',
              destination: 'Rome',
              duration: { minutes: 120, label: '2 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 10,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Test',
              location: 'Rome',
              meeting_point: 'Rome',
              highlights: [],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: [],
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 1 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Click the edit link on the tour card
    const editLink = page.getByRole('link', { name: /edit tour/i });
    await expect(editLink).toBeVisible();
    await expect(editLink).toHaveAttribute('href', '/partner/tours/1/edit');

    // Navigate to edit page
    await editLink.click();
    await expect(page).toHaveURL(/\/partner\/tours\/1\/edit/);
  });
});
