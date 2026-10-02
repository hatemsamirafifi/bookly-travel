import { test, expect } from '@playwright/test';

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
