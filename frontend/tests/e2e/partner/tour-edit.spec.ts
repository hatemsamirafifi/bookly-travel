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

test.describe('Partner Tour Edit Workflow', () => {
  test('reorders English itinerary and gallery, selects cover, previews and saves the same order', async ({ page }) => {
    let savedPayload: Record<string, unknown> | null = null;
    let savedItinerary: Array<{ day: number; title: string; stops: Array<{ title: string }> }> = [
      { day: 1, title: 'Arrival day', stops: [{ title: 'Meet the guide' }] },
      { day: 2, title: 'Explore Rome', stops: [{ title: 'Colosseum' }] },
    ];
    let savedMedia = [
      { id: 1, url: 'http://cdn.bookly.test/tours/rome-cover.jpg', sort_order: 0 },
      { id: 2, url: 'http://cdn.bookly.test/tours/rome-second.jpg', sort_order: 1 },
      { id: 3, url: 'http://cdn.bookly.test/tours/rome-third.jpg', sort_order: 2 },
    ];
    let savedCover = savedMedia[0].url;
    await page.route('**/api/partner/tours/98', async (route) => {
      if (route.request().method() === 'PUT') {
        savedPayload = route.request().postDataJSON();
        const source = (savedPayload as { translations: { en: { itinerary: typeof savedItinerary } } }).translations.en;
        const images = (savedPayload as { media: Array<{ url: string; is_cover: boolean }> }).media;
        savedItinerary = source.itinerary;
        savedMedia = images.map((image, index) => ({ id: index + 1, url: image.url, sort_order: index }));
        savedCover = images.find((image) => image.is_cover)?.url ?? images[0]?.url ?? '';
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 98 } }) });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ data: {
          id: 98, category_id: 1, slug: 'ordered-source-tour', location: 'Rome',
          duration_minutes: 120, group_size_min: 1, group_size_max: 10, status: 'draft',
          cover_image_url: savedCover,
          media: savedMedia,
          translations: [{ locale: 'en', title: 'Ordered tour', description: 'English source description',
            highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '',
            itinerary: savedItinerary,
          }],
        } }),
      });
    });

    await page.goto('/en/partner/tours/98/edit');
    await page.getByRole('button', { name: 'Move day down' }).first().click();
    await page.getByRole('button', { name: 'Move down' }).first().click();
    await page.getByRole('button', { name: 'Set Cover' }).first().click();
    await page.getByRole('button', { name: 'Preview tour' }).click();
    await expect(page.getByRole('heading', { name: 'Explore Rome' })).toBeVisible();
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await expect.poll(() => savedPayload).not.toBeNull();
    const translations = savedPayload!.translations as { en: { itinerary: Array<{ title: string }> } };
    expect(translations.en.itinerary.map((day) => day.title)).toEqual(['Explore Rome', 'Arrival day']);
    const media = savedPayload!.media as Array<{ url: string; is_cover: boolean }>;
    expect(media.map((image) => image.url)).toEqual([
      'http://cdn.bookly.test/tours/rome-third.jpg',
      'http://cdn.bookly.test/tours/rome-cover.jpg',
      'http://cdn.bookly.test/tours/rome-second.jpg',
    ]);
    expect(media.filter((image) => image.is_cover)).toHaveLength(1);
    await page.reload();
    await expect(page.getByRole('textbox', { name: 'Day title' }).first()).toHaveValue('Explore Rome');
    await expect(page.getByRole('img', { name: 'Cover Image' })).toHaveAttribute('src', /rome-third\.jpg/);
  });

  test('edits English source only while showing generated translation states', async ({ page }) => {
    let savedPayload: { translations?: Record<string, unknown> } | null = null;
    await page.route('**/api/partner/tours/99', async (route) => {
      if (route.request().method() === 'PUT') {
        savedPayload = route.request().postDataJSON();
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { id: 99 } }) });
        return;
      }
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            id: 99, category_id: 1, slug: 'english-source-tour', location: 'Rome',
            duration_minutes: 120, group_size_min: 1, group_size_max: 10,
            status: 'draft', cover_image_url: null, guide_languages: ['de', 'en', 'es'],
            translation_statuses: { es: 'pending', it: 'ready' },
            translations: [
              { locale: 'en', title: 'English source tour', description: 'English source description', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
              { locale: 'it', title: 'Tour italiano', description: 'Descrizione italiana', highlights: [], inclusions: [], exclusions: [], meeting_point: '', cancellation_policy: '' },
            ],
          },
        }),
      });
    });

    await page.goto('/en/partner/tours/99/edit');
    await expect(page.getByText('Translation pending')).toBeVisible();
    await expect(page.getByText('Translation ready')).toBeVisible();
    await expect(page.getByRole('button', { name: 'ES', exact: true })).toHaveCount(0);
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await expect.poll(() => savedPayload).not.toBeNull();
    expect(Object.keys(savedPayload!.translations ?? {})).toEqual(['en']);
  });

  test('should load the edit page for an existing tour', async ({ page }) => {
    // Navigate to edit page for tour ID 1
    await page.goto('/en/partner/tours/1/edit');

    // The edit page should load (TourWizard component)
    // Exact content depends on whether the API returns tour data
    await page.waitForLoadState('networkidle');
  });

  test('should show tour cards with status badges on tours list', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Draft Tour',
              slug: 'draft-tour',
              status: 'draft',
              destination: 'Paris',
              duration: { minutes: 60, label: '1 hour' },
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
              location: 'Paris',
              meeting_point: 'Paris',
              highlights: [],
              inclusions: [],
              exclusions: [],
              cancellation_policy: '',
              languages: [],
            },
            {
              id: 2,
              partner_id: 1,
              title: 'Published Tour',
              slug: 'published-tour',
              status: 'published',
              destination: 'Rome',
              duration: { minutes: 180, label: '3 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 15,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'adventure',
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
          meta: { current_page: 1, last_page: 1, per_page: 12, total: 2 },
        }),
      })
    );
    await page.goto('/en/partner/tours');

    // Both tours should be visible
    await expect(page.getByText('Draft Tour')).toBeVisible();
    await expect(page.getByText('Published Tour')).toBeVisible();

    // Status badges should show correct labels
    await expect(page.getByText('Draft', { exact: true })).toBeVisible();
    await expect(page.getByText('Published', { exact: true })).toBeVisible();
  });

  test('should show tour destination in card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 1,
              partner_id: 1,
              title: 'Florence Highlights',
              slug: 'florence-highlights',
              status: 'published',
              destination: 'Florence, Italy',
              location: 'Florence',
              duration: { minutes: 240, label: '4 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 2,
              max_participants: 12,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Explore Florence.',
              meeting_point: 'Piazza della Signoria',
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

    await expect(page.getByText('Florence, Italy')).toBeVisible();
  });

  test('should link to edit page from tour card', async ({ page }) => {
    await page.route('**/api/partner/tours**', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: [
            {
              id: 42,
              partner_id: 1,
              title: 'Milan City Tour',
              slug: 'milan-city-tour',
              status: 'published',
              destination: 'Milan',
              duration: { minutes: 120, label: '2 hours' },
              media: [],
              pricing_tiers: [],
              availability_rules: [],
              availability_exceptions: [],
              min_participants: 1,
              max_participants: 8,
              created_at: '2026-01-01T00:00:00Z',
              updated_at: '2026-06-01T00:00:00Z',
              category: 'walking',
              description: 'Discover Milan.',
              location: 'Milan',
              meeting_point: 'Duomo',
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

    // Edit link should point to the correct URL
    const editLink = page.getByRole('link', { name: /edit tour/i });
    await expect(editLink).toHaveAttribute('href', '/partner/tours/42/edit');
  });
});

test.describe('Partner Tour Edit - Real API (Spec 019 US1)', () => {
  test.beforeEach(async ({ context }) => {
    await context.addCookies([
      { name: 'bookly_cookie_consent', value: 'true', domain: 'localhost', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: 'nginx', path: '/' },
      { name: 'bookly_cookie_consent', value: 'true', domain: '127.0.0.1', path: '/' },
    ]);
  });

  test('round-trips owned English source with repeated days via real API and reopens', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 edit roundtrip');
    // Establish same-origin document first: API helpers read the partner
    // token from localStorage, which is unavailable on about:blank.
    await page.goto('/en/partner/tours');
    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Rome, Italy',
      duration_value: 3,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      translations: { en: { title, description: LONG_DESCRIPTION } },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    // Repeated valid day numbers and a zero-stop day are accepted and ordered.
    const repeated = await apiPut(page, `/api/partner/tours/${tourId}`, {
      translations: {
        en: {
          itinerary: [
            { day: 2, title: 'Second', description: null, stops: [] },
            { day: 1, title: 'First', description: null, stops: [] },
            { day: 2, title: 'Repeated day stays valid', description: null, stops: [] },
          ],
        },
      },
    });
    expect(repeated.status).toBe(200);
    const stored = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(stored.status).toBe(200);
    const storedDays = (
      ownedEnRow(stored.json).itinerary as Array<{ day: number; title: string }>
    ).map((day) => [day.day, day.title]);
    expect(storedDays).toEqual([
      [2, 'Second'],
      [1, 'First'],
      [2, 'Repeated day stays valid'],
    ]);

    // Edit one field in the browser; omitted source travels untouched.
    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    const savedResponsePromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    await page.locator('#edit-source-meeting-point').fill('Snapshot garden gate');
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await savedResponsePromise;
    await expect(page.getByText('Tour details saved successfully.')).toBeVisible();

    const reread = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(reread.status).toBe(200);
    expect(ownedEnRow(reread.json).meeting_point).toBe('Snapshot garden gate');
    expect(ownedEnRow(reread.json).title).toBe(title);

    await page.reload();
    await expect(page.locator('#edit-source-meeting-point')).toHaveValue('Snapshot garden gate');
    await expect(page.getByRole('textbox', { name: 'Day title' }).nth(2)).toHaveValue(
      'Repeated day stays valid'
    );
  });

  test('preserves nullable source on unrelated save and clears on explicit empty list', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 null preserve');
    await page.goto('/en/partner/tours');
    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Florence, Italy',
      duration_value: 2,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      translations: {
        en: {
          title,
          description: LONG_DESCRIPTION,
          meeting_point: null,
          highlights: null,
          inclusions: ['Live guide'],
        },
      },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    // Nulled source renders empty.
    await expect(page.locator('#edit-source-meeting-point')).toHaveValue('');

    // Unrelated basic save keeps canonical nulls intact (real 200 + GET proof).
    const unrelatedPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    await page.locator('#edit-source-title').fill(`${title} v2`);
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await unrelatedPromise;
    const afterUnrelated = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(afterUnrelated.status).toBe(200);
    expect(ownedEnRow(afterUnrelated.json).meeting_point).toBeNull();
    expect(ownedEnRow(afterUnrelated.json).highlights).toBeNull();

    // Explicitly clearing a list sends [] while other nulls stay null.
    const clearedPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    await page.locator('#edit-source-inclusions').fill('');
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await clearedPromise;
    const afterClear = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(afterClear.status).toBe(200);
    expect(ownedEnRow(afterClear.json).inclusions).toEqual([]);
    expect(ownedEnRow(afterClear.json).meeting_point).toBeNull();
  });

  test('restores a partial owned snapshot with nested EN precedence via real API', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 snapshot');
    await page.goto('/en/partner/tours');
    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Siena, Italy',
      duration_value: 2,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      media: [
        { url: 'https://cdn.example.com/snapshot-cover.jpg', is_cover: true },
        { url: 'https://cdn.example.com/snapshot-gallery.jpg', is_cover: false },
      ],
      translations: {
        en: {
          title,
          description: LONG_DESCRIPTION,
          meeting_point: 'Owned point',
          highlights: ['Owned highlight'],
          inclusions: ['Owned inclusion'],
        },
      },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    const snapshotBaseline = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(snapshotBaseline.status).toBe(200);
    const snapshotMedia = (snapshotBaseline.json as { data: { media: unknown[] } }).data.media;
    expect(snapshotMedia).toHaveLength(2);
    const snapshotPayload = {
      translations: {
        en: {
          title: 'Nested title wins',
          meeting_point: 'Snapshot point',
          highlights: ['Snapshot highlight'],
        },
      },
      title: 'Shorthand title loses',
      translation_statuses: { es: 'ready' },
      source_hash: 'forged',
    };
    const saved = await apiPost(page, `/api/partner/tours/${tourId}/drafts/save`, {
      payload: snapshotPayload,
    });
    expect(saved.status).toBe(200);

    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    await page.getByRole('button', { name: 'Restore saved draft' }).click();
    await expect(page.getByText('Draft snapshot restored. Review before saving.')).toBeVisible();
    // Nested EN wins where present (same-field conflict: nested title beats the
    // shorthand title); shorthand fills only missing fields; omitted source
    // keeps current values (description/inclusions untouched); omitted media
    // retains the gallery; readiness/hash never applied.
    await expect(page.locator('#edit-source-meeting-point')).toHaveValue('Snapshot point');
    await expect(page.locator('#edit-source-title')).toHaveValue('Nested title wins');
    await expect(page.locator('#edit-source-description')).toHaveValue(LONG_DESCRIPTION);

    const putPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT' &&
        resp.status() === 200
    );
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    await putPromise;
    const reread = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(reread.status).toBe(200);
    expect(ownedEnRow(reread.json).meeting_point).toBe('Snapshot point');
    expect(ownedEnRow(reread.json).title).toBe('Nested title wins');
    expect(ownedEnRow(reread.json).description).toBe(LONG_DESCRIPTION);
    expect(ownedEnRow(reread.json).inclusions).toEqual(['Owned inclusion']);
    const restoredData = (reread.json as { data: { media: unknown; translation_statuses: { es: string } } }).data;
    expect(restoredData.media).toEqual(snapshotMedia);
    expect(restoredData.translation_statuses.es).not.toBe('ready');

    // The stored raw snapshot is untouched by the restore.
    const latest = await apiGet(page, `/api/partner/tours/${tourId}/drafts/latest`);
    expect(latest.status).toBe(200);
    expect((latest.json as { payload: unknown }).payload).toEqual(snapshotPayload);
  });

  test('shows localized cleared-title validation in EN/ES/IT via real API', async ({ page }) => {
    const title = uniqueTitle('US1 cleared title');
    await page.goto('/en/partner/tours');
    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Rome, Italy',
      duration_value: 2,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      translations: { en: { title, description: LONG_DESCRIPTION } },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    // Baseline canonical source/media for exact-unchanged proof after rejections.
    const baseline = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(baseline.status).toBe(200);
    const baselineData = (baseline.json as { data: Record<string, unknown> }).data;

    const expected: Record<string, { field: string; summary: string; save: string }> = {
      en: {
        field: 'Title is required.',
        summary: 'Please review the highlighted fields below.',
        save: 'Save Tour Details',
      },
      es: {
        field: 'El título es obligatorio.',
        summary: 'Revisa los campos destacados a continuación.',
        save: 'Guardar detalles del tour',
      },
      it: {
        field: 'Il titolo è obbligatorio.',
        summary: 'Controlla i campi evidenziati qui sotto.',
        save: 'Salva i dettagli del tour',
      },
    };
    for (const locale of ['en', 'es', 'it'] as const) {
      await page.goto(`/${locale}/partner/tours/${tourId}/edit`);
      await expect(page.locator('#edit-source-title')).toHaveValue(title);
      // Legitimate cleared title: the production form carries noValidate so
      // the empty value reaches the real API (empty normalizes to null and
      // the installed `string` rule answers 422); native required metadata
      // stays on the input for assistive technology.
      await page.locator('#edit-source-title').fill('');
      const rejectedPromise = page.waitForResponse(
        (resp) =>
          resp.url().includes(`/api/partner/tours/${tourId}`) &&
          resp.request().method() === 'PUT'
      );
      await page.getByRole('button', { name: expected[locale].save }).click();
      const rejected = await rejectedPromise;
      expect(rejected.status()).toBe(422);
      // Actual nested EN 422 surfaces as localized field + focused summary.
      const titleField = page.locator('#edit-source-title');
      await expect(titleField).toHaveAttribute('aria-invalid', 'true');
      const describedBy = await titleField.getAttribute('aria-describedby');
      expect(describedBy ?? '').toContain('edit-title-errors');
      const fieldError = page.locator('#edit-title-errors', { hasText: expected[locale].field });
      await expect(fieldError).toBeVisible();
      const alertBox = page.locator('[role="alert"]', { hasText: expected[locale].summary });
      await expect(alertBox).toBeVisible();
      await expect(alertBox).toBeFocused();
      // No raw backend English leaks into the UI.
      await expect(page.getByText('The translations.en.title field must be a string.')).toHaveCount(0);
    }

    // The rejected clears never persisted: exact canonical source/media unchanged.
    const reread = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(reread.status).toBe(200);
    expect(ownedEnglishSource(reread.json)).toEqual(ownedEnglishSource(baseline.json));
    const rereadData = (reread.json as { data: Record<string, unknown> }).data;
    expect(rereadData.media).toEqual(baselineData.media);
    expect(rereadData.cover_image_url).toBe(baselineData.cover_image_url);
  });

  test('rejects fractional durations and over-long list items with localized errors via real API', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 invalid fields');
    await page.goto('/en/partner/tours');
    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Rome, Italy',
      duration_value: 2,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      translations: { en: { title, description: LONG_DESCRIPTION } },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    // Baseline canonical source/media for exact-unchanged proof after rejections.
    const baseline = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(baseline.status).toBe(200);
    const baselineData = (baseline.json as { data: Record<string, unknown> }).data;

    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    await page.getByRole('button', { name: 'Add day' }).click();
    await page.getByRole('textbox', { name: 'Day title' }).fill('Bad day');
    await page.getByRole('button', { name: 'Add stop' }).click();
    await page.getByRole('textbox', { name: 'Stop title' }).fill('Bad stop');
    // Legitimate fractional entry: the production form carries noValidate so
    // the authored 1.5 travels to the real API, whose `integer` rule answers
    // the 422 the UI must localize. No DOM attributes are removed in tests.
    await page.getByLabel('Stop duration (minutes)').fill('1.5');
    const fractionalPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    const fractionalRejected = await fractionalPromise;
    expect(fractionalRejected.status()).toBe(422);
    const durationField = page.getByLabel('Stop duration (minutes)');
    await expect(durationField).toHaveAttribute('aria-invalid', 'true');
    const durationError = page.locator('#itinerary-day-0-stop-0-duration-error');
    await expect(durationError).toContainText('Duration must be a whole number of minutes.');
    const durationSummary = page.locator('[role="alert"]', {
      hasText: 'Please review the highlighted fields below.',
    });
    await expect(durationSummary).toBeFocused();

    // Fix the duration, then break a list item: real 422 surfaces localized.
    await page.getByLabel('Stop duration (minutes)').fill('');
    await page.locator('#edit-source-highlights').fill('x'.repeat(501));
    const listRejectedPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}`) &&
        resp.request().method() === 'PUT'
    );
    await page.getByRole('button', { name: 'Save Tour Details' }).click();
    const listRejected = await listRejectedPromise;
    expect(listRejected.status()).toBe(422);
    const highlightsField = page.locator('#edit-source-highlights');
    await expect(highlightsField).toHaveAttribute('aria-invalid', 'true');
    const highlightsDescribedBy = await highlightsField.getAttribute('aria-describedby');
    expect(highlightsDescribedBy ?? '').toContain('edit-highlights-errors');
    await expect(page.locator('#edit-highlights-errors')).toContainText('Each item must be 500 characters or fewer.');
    const listSummary = page.locator('[role="alert"]', {
      hasText: 'Please review the highlighted fields below.',
    });
    await expect(listSummary).toBeFocused();

    // Neither invalid attempt persisted anything: exact canonical source/media unchanged.
    const reread = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(reread.status).toBe(200);
    expect(ownedEnglishSource(reread.json)).toEqual(ownedEnglishSource(baseline.json));
    const rereadData = (reread.json as { data: Record<string, unknown> }).data;
    expect(rereadData.media).toEqual(baselineData.media);
    expect(rereadData.cover_image_url).toBe(baselineData.cover_image_url);
  });

  test('denies cross-owner reads with 404 for a real second principal and localizes denied loads', async ({
    page,
  }) => {
    const stamp = Date.now().toString(36);
    await page.goto('/en/partner/tours');
    const registration = await page.request.fetch(
      `${new URL(page.url()).origin}/api/public/auth/partners/register`,
      {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        data: JSON.stringify({
          name: 'Second Partner',
          email: `second-${stamp}@bookly.test`,
          password: 'Password123!',
          password_confirmation: 'Password123!',
          company_name: 'Second Tours',
          contact_email: `second-${stamp}@bookly.test`,
          contact_phone: '+10000000000',
          business_description: 'A second operator for cross-owner denial checks.',
          business_address: {
            street: '1 Other Street',
            city: 'Milan',
            postal_code: '20100',
            country: 'IT',
          },
          payout_country: 'IT',
        }),
      }
    );
    expect(registration.status()).toBe(201);
    const registrationBody = (await registration.json()) as { data: { token: string } };
    const secondToken = registrationBody.data.token;
    expect(secondToken.length).toBeGreaterThan(0);

    // Actual other-partner principal reading an owned tour: real 404, unchanged tour.
    const crossRead = await page.request.fetch(
      `${new URL(page.url()).origin}/api/partner/tours/1`,
      {
        method: 'GET',
        headers: { Authorization: `Bearer ${secondToken}`, Accept: 'application/json' },
      }
    );
    expect(crossRead.status()).toBe(404);

    // Unknown tour loads show page-localized failure, never raw English.
    await page.goto('/en/partner/tours/999999/edit');
    await expect(
      page.getByText("Couldn't load tour details. Please try again.")
    ).toBeVisible();
  });

  test('failed submit retains the owned draft id and retry submits the same tour', async ({
    page,
  }) => {
    const title = uniqueTitle('US1 submit retry');
    await page.goto('/en/partner/tours');
    const before = await apiGet(page, '/api/partner/tours?per_page=100');
    expect(before.status).toBe(200);
    const totalBefore = (before.json as { meta: { total: number } }).meta.total;

    const created = await apiPost(page, '/api/partner/tours', {
      title,
      description: LONG_DESCRIPTION,
      category: 'walking',
      destination: 'Rome, Italy',
      duration_value: 2,
      duration_unit: 'hour',
      difficulty_level: 'easy',
      translations: { en: { title, description: LONG_DESCRIPTION } },
    });
    expect(created.status).toBe(201);
    const tourId = (created.json as { data: { id: number } }).data.id;

    await page.goto(`/en/partner/tours/${tourId}/edit`);
    await expect(page.locator('#edit-source-title')).toHaveValue(title);
    // Guarded submission refuses the incomplete tour: real API 422, same id kept.
    await page.getByRole('button', { name: 'Submit For Review' }).click();
    await expect(
      page.getByText("Couldn't submit the tour for review. Please try again.")
    ).toBeVisible();
    expect(page.url()).toContain(`/partner/tours/${tourId}/edit`);

    // Satisfy the server guards through the real API, then retry in the browser.
    const tier = await apiPost(page, `/api/partner/tours/${tourId}/pricing`, {
      name: 'Adult',
      price: 45,
      min_participants: 1,
      max_participants: 10,
    });
    expect(tier.status).toBe(201);
    const cover = await apiPut(page, `/api/partner/tours/${tourId}`, {
      cover_image_url: 'https://cdn.example.com/us1-submit-cover.jpg',
      media: [{ url: 'https://cdn.example.com/us1-submit-cover.jpg', is_cover: true }],
    });
    expect(cover.status).toBe(200);

    const submittedPromise = page.waitForResponse(
      (resp) =>
        resp.url().includes(`/api/partner/tours/${tourId}/submit`) &&
        resp.request().method() === 'POST' &&
        resp.status() === 200
    );
    await page.getByRole('button', { name: 'Submit For Review' }).click();
    await submittedPromise;
    await expect(page.getByText('Tour submitted for admin review successfully.')).toBeVisible();

    const detail = await apiGet(page, `/api/partner/tours/${tourId}`);
    expect(detail.status).toBe(200);
    expect((detail.json as { data: { status: string } }).data.status).toBe('pending_review');

    // No duplicate tour was materialized by the failed-then-retried submit.
    // (List rows carry no top-level title; match on the owned id + totals.)
    const after = await apiGet(page, '/api/partner/tours?per_page=100');
    expect(after.status).toBe(200);
    const afterData = ((after.json as { data: Array<{ id: number }> }).data ?? []);
    expect(afterData.filter((tour) => tour.id === tourId)).toHaveLength(1);
    expect((after.json as { meta: { total: number } }).meta.total).toBe(totalBefore + 1);
  });

  test('renders the edit page across EN/ES/IT and the 320/390/768/1024/1440 viewport matrix', async ({
    page,
  }, testInfo) => {
    for (const locale of ['en', 'es', 'it'] as const) {
      // 320 stays as supplemental narrow-width coverage; 390 is the
      // Constitution VI required narrow viewport.
      for (const width of [320, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`/${locale}/partner/tours/1/edit`);
        await expect(page.locator('#edit-source-title')).toBeVisible();
        await page.screenshot({
          path: testInfo.outputPath(`edit-${locale}-${width}.png`),
        });
      }
    }
  });
});
