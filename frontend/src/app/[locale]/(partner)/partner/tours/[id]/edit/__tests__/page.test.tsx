import type { ReactNode } from 'react';
import { Suspense, act } from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import PartnerTourEditPage from '../page';
import { getLatestTourDraft, saveTourDraft } from '@/lib/api/partner';
import { NotFoundError } from '@/lib/api/client';
import type { TourItineraryDay } from '@/lib/api/types';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../../../../../../messages/en.json';
import es from '../../../../../../../../../messages/es.json';
import itMessages from '../../../../../../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

const catalogs = { en, es, it: itMessages };
type Locale = keyof typeof catalogs;
const englishForm = en.partner.tours.form;

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href }: { children: ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

jest.mock('@/lib/auth/token', () => ({
  getAuthToken: () => 'test-token',
}));

jest.mock('@/lib/api/partner', () => {
  const actual = jest.requireActual('@/lib/api/partner');
  return {
    ...actual,
    getLatestTourDraft: jest.fn(),
    saveTourDraft: jest.fn(),
  };
});

const mockedGetLatestTourDraft = getLatestTourDraft as jest.Mock;
const mockedSaveTourDraft = saveTourDraft as jest.Mock;

if (typeof window !== 'undefined' && !window.requestAnimationFrame) {
  window.requestAnimationFrame = ((cb: FrameRequestCallback) => {
    cb(0);
    return 0;
  }) as typeof window.requestAnimationFrame;
}

interface MockEnRow {
  locale: string;
  title: string;
  description: string;
  highlights: string[] | null;
  inclusions: string[] | null;
  exclusions: string[] | null;
  meeting_point: string | null;
  cancellation_policy: string | null;
  important_information: string[] | null;
  itinerary: TourItineraryDay[] | null;
}

interface MockTour {
  id: number;
  source_locale?: 'en' | 'es' | 'it';
  category_id: number;
  slug: string;
  location: string;
  duration_minutes: number;
  group_size_min: number;
  group_size_max: number;
  status: string;
  cover_image_url: string;
  guide_languages: string[];
  translation_statuses: { es: 'pending'; it: 'pending' };
  media: Array<{ id: number; url: string; sort_order: number }>;
  translations: MockEnRow[];
}

const ownedTour: MockTour = {
  id: 42,
  category_id: 3,
  slug: 'owned-tour',
  location: 'Florence, Italy',
  duration_minutes: 180,
  group_size_min: 2,
  group_size_max: 7,
  status: 'draft',
  cover_image_url: 'https://cdn.example.com/cover.jpg',
  guide_languages: ['en'],
  translation_statuses: { es: 'pending', it: 'pending' },
  media: [
    { id: 1, url: 'https://cdn.example.com/cover.jpg', sort_order: 0 },
    { id: 2, url: 'https://cdn.example.com/second.jpg', sort_order: 1 },
  ],
  translations: [
    {
      locale: 'en',
      title: 'Owned tour',
      description: 'Owned description for the tour reopen test.',
      highlights: ['Old highlight'],
      inclusions: ['Old inclusion'],
      exclusions: ['Old exclusion'],
      meeting_point: 'Old meeting point',
      cancellation_policy: 'Old policy',
      important_information: ['Old info'],
      itinerary: [{ day: 1, title: 'Old day', description: null, stops: [] }],
    },
  ],
};

const ownedTourWithNulls: MockTour = {
  ...ownedTour,
  translations: [
    {
      ...ownedTour.translations[0],
      meeting_point: null,
      highlights: null,
      important_information: null,
      itinerary: null,
    },
  ],
};

function mockFetchTour(tour: MockTour = ownedTour) {
  const putBodies: unknown[] = [];
  global.fetch = jest.fn(async (url: string, init?: RequestInit) => {
    if (init?.method === 'PUT' && url.includes('/api/partner/tours/42')) {
      putBodies.push(init.body !== undefined ? JSON.parse(init.body as string) : null);
      return { ok: true, status: 200, json: async () => ({ data: { id: 42 } }) } as Response;
    }
    return { ok: true, status: 200, json: async () => ({ data: tour }) } as Response;
  }) as typeof fetch;
  return putBodies;
}

interface PutBody extends Record<string, unknown> {
  translations: { en: Record<string, unknown> };
  media?: unknown;
}

function lastPutBody(putBodies: unknown[]): PutBody {
  const raw: unknown = putBodies[putBodies.length - 1];
  if (typeof raw !== 'object' || raw === null || !('translations' in raw)) {
    throw new Error('Expected a PUT body with translations');
  }
  const translations: unknown = raw.translations;
  if (typeof translations !== 'object' || translations === null || !('en' in translations)) {
    throw new Error('Expected a PUT body with nested EN source');
  }
  const en: unknown = translations.en;
  if (typeof en !== 'object' || en === null || Array.isArray(en)) {
    throw new Error('Expected a PUT body with an EN object');
  }
  const record: Record<string, unknown> = {};
  for (const key of Object.keys(en)) {
    record[key] = (en as Record<string, unknown>)[key];
  }
  return { ...raw, translations: { en: record } };
}

async function renderPage(locale: Locale = 'en') {
  const params = Promise.resolve({ id: '42', locale });
  await act(async () => {
    render(
      <NextIntlClientProvider locale={locale} messages={catalogs[locale]} timeZone="UTC">
        <Suspense fallback={<div>Loading test harness...</div>}>
          <PartnerTourEditPage params={params} />
        </Suspense>
      </NextIntlClientProvider>
    );
  });
}

describe('PartnerTourEditPage snapshot restore (Spec 019)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockFetchTour();
  });

  it.each([
    [undefined, 'en', 'Owned legacy tour'],
    ['es', 'es', 'Recorrido guardado'],
  ] as const)('opens an existing tour with source %s directly in its authored editor', async (storedLocale, authoredLocale, title) => {
    mockFetchTour({
      ...ownedTour,
      ...(storedLocale && { source_locale: storedLocale }),
      translations: [{ ...ownedTour.translations[0], locale: authoredLocale, title }],
    });

    await renderPage();

    expect(await screen.findByDisplayValue(title)).toBeVisible();
    expect(screen.queryByRole('radiogroup', { name: englishForm.sourceLanguageLabel })).not.toBeInTheDocument();
  });

  it('retains specific English alt through snapshot media restoration and the owned update', async () => {
    const putBodies = mockFetchTour();
    mockedGetLatestTourDraft.mockResolvedValue({
      id: 6,
      tour_id: 42,
      partner_id: 9,
      status: 'draft',
      payload: {
        media: [
          { url: ownedTour.cover_image_url, is_cover: true, alt_text: 'Sunrise above the harbor' },
          { url: ownedTour.media[1].url, is_cover: false, alt_text: null },
        ],
      },
    });
    await renderPage();
    await screen.findByDisplayValue('Owned tour');
    fireEvent.click(screen.getByRole('button', { name: englishForm.restoreDraft }));
    await screen.findByText(englishForm.draftRestored);
    fireEvent.click(screen.getByRole('button', { name: englishForm.saveDetails }));
    await waitFor(() => expect(putBodies).toHaveLength(1));
    expect(lastPutBody(putBodies).media).toEqual([
      expect.objectContaining({ url: ownedTour.cover_image_url, is_cover: true, alt_text: 'Sunrise above the harbor' }),
      expect.objectContaining({ url: ownedTour.media[1].url, is_cover: false, alt_text: null }),
    ]);
  });

  it('merges a partial owned snapshot per field and retains omitted source and media', async () => {
    const snapshotPayload = {
      translations: {
        en: {
          title: 'Nested title wins',
          meeting_point: 'Snapshot point',
          highlights: ['Snapshot highlight'],
          meeting_point_decoy: 'Opaque extra snapshot content',
        },
      },
      title: 'Shorthand title loses',
      translation_statuses: { es: 'ready' },
      source_hash: 'forged',
    };
    mockedGetLatestTourDraft.mockResolvedValue({
      id: 5,
      tour_id: 42,
      partner_id: 9,
      payload: snapshotPayload,
      status: 'draft',
    });
    await renderPage();
    await waitFor(() => expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: englishForm.restoreDraft }));
    await waitFor(() => expect(screen.getByText(englishForm.draftRestored)).toBeInTheDocument());

    // Same-field conflict: explicit nested EN wins over shorthand; shorthand
    // fills only fields missing from nested EN; omitted source keeps current
    // values; omitted media retains the gallery; readiness/hash never applied.
    expect(screen.getByDisplayValue('Snapshot point')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Nested title wins')).toBeInTheDocument();
    expect(screen.queryByDisplayValue('Shorthand title loses')).not.toBeInTheDocument();
    expect(screen.getByDisplayValue('Owned description for the tour reopen test.')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Snapshot highlight')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Old inclusion')).toBeInTheDocument();

    // Saving sends the merged owned source; media omission retains media.
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() => expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/partner/tours/42'),
      expect.objectContaining({ method: 'PUT' })
    ));
    const putCalls = (global.fetch as jest.Mock).mock.calls.filter(
      (call) => (call[1] as RequestInit)?.method === 'PUT'
    );
    const body = JSON.parse(putCalls[0][1].body as string) as {
      translations: { en: Record<string, unknown> };
      media?: unknown;
    };
    expect(body.translations.en.meeting_point).toBe('Snapshot point');
    expect(body.translations.en.title).toBe('Nested title wins');
    expect(body.translations.en.description).toBe('Owned description for the tour reopen test.');
    expect(body.translations.en).not.toHaveProperty('translation_statuses');
    expect(body.translations.en).not.toHaveProperty('source_hash');
    expect(body).not.toHaveProperty('media');

    // The stored raw snapshot is untouched and no draft write happens.
    expect(snapshotPayload).toEqual({
      translations: {
        en: {
          title: 'Nested title wins',
          meeting_point: 'Snapshot point',
          highlights: ['Snapshot highlight'],
          meeting_point_decoy: 'Opaque extra snapshot content',
        },
      },
      title: 'Shorthand title loses',
      translation_statuses: { es: 'ready' },
      source_hash: 'forged',
    });
    expect(mockedSaveTourDraft).not.toHaveBeenCalled();
  });

  it('applies explicit null and empty-list clearing from the snapshot', async () => {
    const putBodies = mockFetchTour();
    mockedGetLatestTourDraft.mockResolvedValue({
      id: 6,
      tour_id: 42,
      partner_id: 9,
      payload: { translations: { en: { meeting_point: null, highlights: [] } } },
      status: 'draft',
    });
    await renderPage();
    await waitFor(() => expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: englishForm.restoreDraft }));
    await waitFor(() => expect(screen.getByText(englishForm.draftRestored)).toBeInTheDocument());

    expect(screen.getByLabelText(/Meeting Point/)).toHaveValue('');
    expect(screen.getByLabelText(englishForm.highlights)).toHaveValue('');
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() => expect(putBodies).toHaveLength(1));
    const body = lastPutBody(putBodies);
    expect(body.translations.en.meeting_point).toBeNull();
    expect(body.translations.en.highlights).toEqual([]);
  });

  it('sends untouched canonical nulls back as null on an unrelated save', async () => {
    const putBodies = mockFetchTour(ownedTourWithNulls);
    await renderPage();
    await waitFor(() => expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument());

    // Nulled source renders empty but is preserved as null, not [].
    expect(screen.getByLabelText(/Meeting Point/)).toHaveValue('');
    fireEvent.change(screen.getByDisplayValue('Florence, Italy'), { target: { value: 'Siena, Italy' } });

    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() => expect(putBodies.length).toBeGreaterThan(0));

    const body = lastPutBody(putBodies);
    expect(body.location).toBe('Siena, Italy');
    expect(body.translations.en.meeting_point).toBeNull();
    expect(body.translations.en.highlights).toBeNull();
    expect(body.translations.en.important_information).toBeNull();
    expect(body.translations.en.itinerary).toBeNull();
    expect(body.translations.en.title).toBe('Owned tour');
    expect(body).not.toHaveProperty('media');
  });

  it('persists an explicitly edited empty list while retaining other canonical nulls', async () => {
    const putBodies = mockFetchTour(ownedTourWithNulls);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');
    const highlights = screen.getByLabelText(englishForm.highlights);
    fireEvent.change(highlights, { target: { value: 'Temporary highlight' } });
    fireEvent.change(highlights, { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() => expect(putBodies).toHaveLength(1));
    const body = lastPutBody(putBodies);
    expect(body.translations.en.highlights).toEqual([]);
    expect(body.translations.en.itinerary).toBeNull();
    expect(body.translations.en.meeting_point).toBeNull();
  });

  it.each(['en', 'es', 'it'] as const)('renders source labels and focused field feedback from the %s catalog', async (locale) => {
    await renderPage(locale);
    await screen.findByDisplayValue('Owned tour');
    const form = catalogs[locale].partner.tours.form;
    const errors = catalogs[locale].partner.tours.errors;
    for (const key of ['title', 'description', 'meetingPoint', 'cancellationPolicy'] as const) {
      expect(screen.getByLabelText(`${form[key]} (EN)`)).toBeInTheDocument();
    }
    for (const key of ['highlights', 'inclusions', 'exclusions', 'importantInformation'] as const) {
      expect(screen.getByLabelText(form[key])).toBeInTheDocument();
    }
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: false, status: 422,
      json: async () => ({ errors: { 'translations.en.highlights': ['The translations.en.highlights must not have more than 30 items.'] } }),
    });
    fireEvent.click(screen.getByRole('button', { name: form.saveDetails }));
    await screen.findByText(errors.highlightsMax);
    const summary = screen.getByText(form.errorSummary).closest('[role="alert"]');
    expect(document.activeElement).toBe(summary);
    expect(screen.queryByText('The translations.en.highlights must not have more than 30 items.')).not.toBeInTheDocument();
  });

  it('focuses an announced error summary when saving fails validation', async () => {
    mockFetchTour();
    await renderPage();
    await waitFor(() => expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument());

    (global.fetch as jest.Mock).mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === 'PUT') {
        return {
          ok: false,
          status: 422,
          json: async () => ({
            message: 'Validation failed',
            errors: { 'translations.en.title': ['Title is required.'] },
          }),
        } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ data: ownedTour }) } as Response;
    });

    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    const message = await screen.findByText(englishForm.errorSummary);
    const summary = message.closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(document.activeElement).toBe(summary);
    // The nested path maps onto the actual title field.
    expect(screen.getByText(en.partner.tours.errors.titleRequired)).toBeInTheDocument();
  });

  it('lets empty titles reach server validation via noValidate while retaining required metadata', async () => {
    mockFetchTour();
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    // Real form carries noValidate so custom localized server feedback runs;
    // required/step metadata stays for assistive technology.
    const form = screen.getByRole('button', { name: 'Save Tour Details' }).closest('form');
    expect(form).not.toBeNull();
    expect(form).toHaveAttribute('noValidate');
    expect(screen.getByLabelText(/Title \(EN\)/i)).toHaveAttribute('required');
  });

  it.each(['en', 'es', 'it'] as const)('localizes the actual cleared-title string-rule 422 in %s with focused summary', async (locale) => {
    mockFetchTour();
    await renderPage(locale);
    await screen.findByDisplayValue('Owned tour');
    const form = catalogs[locale].partner.tours.form;
    const errors = catalogs[locale].partner.tours.errors;

    // Actual live-API response for a cleared title (empty string normalizes
    // to null; installed `string` rule reports "must be a string").
    (global.fetch as jest.Mock).mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === 'PUT') {
        return {
          ok: false,
          status: 422,
          json: async () => ({
            message: 'The translations.en.title field must be a string.',
            errors: { 'translations.en.title': ['The translations.en.title field must be a string.'] },
          }),
        } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ data: ownedTour }) } as Response;
    });

    fireEvent.change(screen.getByLabelText(`${form.title} (EN)`), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: form.saveDetails }));
    await screen.findByText(errors.titleRequired);
    const summary = screen.getByText(form.errorSummary).closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(document.activeElement).toBe(summary);
    expect(screen.queryByText('The translations.en.title field must be a string.')).not.toBeInTheDocument();
  });

  it.each([
    { locale: 'en' as const, saveLabel: 'Save Tour Details' },
    { locale: 'es' as const, saveLabel: 'Guardar detalles del tour' },
    { locale: 'it' as const, saveLabel: 'Salva i dettagli del tour' },
  ])('offers a localized save action and announces success in $locale', async ({ locale, saveLabel }) => {
    await renderPage(locale);
    await screen.findByDisplayValue('Owned tour');
    expect(screen.getByRole('heading', { name: catalogs[locale].partner.tours.editTour })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: saveLabel }));
    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent(catalogs[locale].partner.tours.form.saveSucceeded);
  });

  it('passes all nine English source fields and saved statuses to the preview', async () => {
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    // Authorized US2 integration: the actual edit preview caller forwards
    // the full current English source (not just title/description/itinerary)
    // plus the sanitized owned translation_statuses via the shared DTO.
    fireEvent.click(screen.getByRole('button', { name: englishForm.previewTour }));
    const preview = screen.getByLabelText(englishForm.previewTour);
    expect(preview.querySelector('h2')).toHaveTextContent('Owned tour');
    expect(preview.querySelector('h2')).toHaveAttribute('lang', 'en');
    for (const text of [
      'Owned description for the tour reopen test.',
      'Old highlight',
      'Old inclusion',
      'Old exclusion',
      'Old info',
      'Old meeting point',
      'Old policy',
      'Old day',
    ]) {
      expect(preview.textContent).toContain(text);
    }
    expect(preview.textContent).toContain('Day 1:');
    expect(preview.textContent).toContain(`ES: ${englishForm.translationStatus.pending}`);
    expect(preview.textContent).toContain(`IT: ${englishForm.translationStatus.pending}`);
    expect(preview.textContent).toContain(englishForm.previewReadinessNote);
    expect(preview.textContent).not.toMatch(/source_hash|provider|job_state/i);
  });

  it('reports no draft only on 404 and keeps the form on other failures', async () => {
    mockedGetLatestTourDraft.mockRejectedValueOnce(new NotFoundError('missing'));
    await renderPage();
    await waitFor(() => expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: englishForm.restoreDraft }));
    await waitFor(() => expect(screen.getByText(englishForm.noDraft)).toBeInTheDocument());
    expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument();

    mockedGetLatestTourDraft.mockRejectedValueOnce(new Error('network down'));
    fireEvent.click(screen.getByRole('button', { name: englishForm.restoreDraft }));
    await waitFor(() => expect(screen.getByText(englishForm.restoreFailed)).toBeInTheDocument());
    expect(screen.getByDisplayValue('Owned tour')).toBeInTheDocument();
  });
});

describe('PartnerTourEditPage published revision governance (EGM-003)', () => {
  const revisionEn = en.partner.tours.revision;

  const approvedRevision = {
    id: 12,
    revision_number: 3,
    kind: 'legacy_baseline',
    state: 'approved',
    payload_hash: 'hash-12',
    created_at: '2026-11-01T00:00:00Z',
  };

  const liveOptions = [
    {
      id: 5,
      title: 'Standard group',
      pickup_instructions: 'Hotel pickup',
      guide_languages: ['en'],
      price_amount: 5000,
      group_size_max: 10,
      start_time: null,
      weekdays: null,
    },
  ];

  function candidateMetadata(state: string) {
    return {
      id: 15,
      revision_number: 4,
      kind: 'partner_edit',
      state,
      payload_hash: 'hash-15',
      base_revision_id: 12,
      submitted_at: state === 'proposed' ? '2026-11-12T00:00:00Z' : null,
      change_summary: null,
    };
  }

  function publishedGovernance(overrides: Record<string, unknown> = {}) {
    return {
      source_locale: 'en',
      generation_epoch: 3,
      approved_revision: { ...approvedRevision },
      active_candidate: null,
      open_requests: [],
      ...overrides,
    };
  }

  function publishedTour(overrides: Record<string, unknown> = {}) {
    return {
      ...ownedTour,
      status: 'published',
      options: liveOptions,
      content_governance: publishedGovernance(),
      ...overrides,
    };
  }

  const openRequest = {
    id: 9,
    context: 'published_review',
    state: 'open',
    reason: 'Please clarify the meeting point for travelers.',
    field_refs: [{ path: 'source.meeting_point', note: 'Add the exact gate' }],
    created_at: '2026-11-12T00:00:00Z',
  };

  const candidatePayload = {
    source: {
      title: 'Candidate title',
      description: '<p>Candidate description for the reopened published candidate.</p>',
      highlights: ['Candidate highlight'],
      inclusions: ['Candidate inclusion'],
      exclusions: [],
      meeting_point: 'Candidate meeting point',
      cancellation_policy: 'Candidate policy',
      important_information: ['Candidate info'],
      itinerary: [{ day: 1, title: 'Candidate day', description: null, stops: [] }],
    },
    presentation: {
      category_id: 3,
      slug: 'owned-tour',
      location: 'Florence, Italy',
      duration_minutes: 180,
      group_size_min: 2,
      group_size_max: 7,
      difficulty_level: 'moderate',
      guide_languages: ['en'],
      cover_image_url: 'https://cdn.example.com/cover.jpg',
    },
    options: [{ id: 5, title: 'Candidate option title', pickup_instructions: 'Candidate pickup' }],
    media: [{ url: 'https://cdn.example.com/cover.jpg', is_cover: true, alt_text: 'Cover alt' }],
  };

  function revisionDetail(state: string) {
    return {
      data: {
        id: 15,
        revision_number: 4,
        kind: 'partner_edit',
        state,
        base_revision_id: 12,
        payload_hash: 'hash-15',
        created_at: '2026-11-12T00:00:00Z',
        submitted_at: null,
        change_summary: null,
        revision_diff: null,
        payload: candidatePayload,
      },
    };
  }

  function revisionMutationResponse(id: number, revisionNumber: number, hash: string) {
    return {
      data: {
        id,
        revision_number: revisionNumber,
        kind: 'partner_edit',
        state: 'draft',
        payload_hash: hash,
        base_revision_id: 12,
        created_at: '2026-11-12T00:00:00Z',
      },
      message: 'ok',
    };
  }

  interface RecordedCall {
    method: string;
    url: string;
    body: Record<string, unknown> | null;
    status: number;
  }

  type OverrideHandler = (call: { method: string; url: string; body: Record<string, unknown> | null })
    => { status: number; body: unknown } | null;

  /** HTTP-boundary mock: the page runs its real partner API wrappers against a
   *  miniature backend that also advances governance state like a real server. */
  function installRevisionApi(
    tourRef: { current: Record<string, unknown> },
    override?: OverrideHandler
  ): RecordedCall[] {
    const calls: RecordedCall[] = [];
    const respond = (status: number, body: unknown) => ({
      ok: status >= 200 && status < 300,
      status,
      json: async () => body,
    }) as Response;

    const setGovernance = (patch: Record<string, unknown>) => {
      const gov = (tourRef.current.content_governance ?? {}) as Record<string, unknown>;
      tourRef.current = { ...tourRef.current, content_governance: { ...gov, ...patch } };
    };

    global.fetch = jest.fn(async (url: unknown, init?: RequestInit) => {
      const u = String(url);
      const method = init?.method ?? 'GET';
      let body: Record<string, unknown> | null = null;
      if (typeof init?.body === 'string') {
        try {
          body = JSON.parse(init.body) as Record<string, unknown>;
        } catch {
          body = null;
        }
      }
      const call: RecordedCall = { method, url: u, body, status: 200 };
      calls.push(call);

      const overridden = override?.(call);
      if (overridden) return respond(overridden.status, overridden.body);

      if (method === 'GET' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(u)) {
        const gov = tourRef.current.content_governance as Record<string, unknown> | undefined;
        const candidate = gov?.active_candidate as { state?: string } | undefined;
        return respond(200, revisionDetail(candidate?.state ?? 'draft'));
      }
      if (method === 'POST' && /\/api\/partner\/tours\/42\/revisions$/.test(u)) {
        setGovernance({ active_candidate: candidateMetadata('draft') });
        return respond(201, revisionMutationResponse(15, 4, 'hash-15'));
      }
      if (method === 'PUT' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(u)) {
        setGovernance({
          active_candidate: { ...candidateMetadata('draft'), id: 16, revision_number: 5, payload_hash: 'hash-16' },
        });
        return respond(200, revisionMutationResponse(16, 5, 'hash-16'));
      }
      if (method === 'POST' && /\/api\/partner\/tours\/42\/revisions\/15\/submit$/.test(u)) {
        setGovernance({ active_candidate: candidateMetadata('proposed') });
        return respond(200, { data: { id: 15, revision_number: 4, state: 'proposed' }, message: 'ok' });
      }
      if (method === 'POST' && /\/api\/partner\/tours\/42\/revisions\/15\/withdraw$/.test(u)) {
        setGovernance({ active_candidate: null });
        return respond(200, { data: { id: 15, state: 'withdrawn' }, message: 'ok' });
      }
      if (method === 'POST' && /\/api\/partner\/tours\/42\/revision-requests\/9\/respond$/.test(u)) {
        setGovernance({ open_requests: [] });
        return respond(200, { data: { id: 9, state: 'answered' }, message: 'ok' });
      }
      if (method === 'PUT' && /\/api\/partner\/tours\/42$/.test(u)) {
        return respond(200, { data: { id: 42 } });
      }
      if (method === 'GET' && u.includes('/api/partner/tours/42')) {
        return respond(200, { data: tourRef.current });
      }
      return respond(404, { message: 'Not found.' });
    }) as typeof fetch;
    return calls;
  }

  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('blocks a mixed published save with localized guidance and sends no request at all', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Governed new title' } });
    fireEvent.change(screen.getByLabelText(englishForm.optionPrice), { target: { value: '62.5' } });

    const callsBefore = calls.length;
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await screen.findByText(revisionEn.mixedBlocked);

    expect(calls.length).toBe(callsBefore);
    expect(screen.getByDisplayValue('Governed new title')).toBeInTheDocument();
    expect(screen.getByLabelText(englishForm.optionPrice)).toHaveValue(62.5);
  });

  it('saves an explicit content draft as a proposal over the observed approved base', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Governed new title' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'POST' && c.url.endsWith('/api/partner/tours/42/revisions'))).toBe(true)
    );
    const created = calls.find((c) => c.method === 'POST' && c.url.endsWith('/api/partner/tours/42/revisions'));
    expect(created?.body).toMatchObject({
      base_revision_id: 12,
      translations: { en: { title: 'Governed new title' } },
    });
    // Only governed content travels: no commercial option fields, no media
    // round-trip, no presentation echo, and no operational keys.
    expect(created?.body).not.toHaveProperty('options');
    expect(created?.body).not.toHaveProperty('media');
    expect(created?.body).not.toHaveProperty('presentation');
    expect(created?.body).not.toHaveProperty('guide_languages');
    expect(created?.body).not.toHaveProperty('location');
    // The legacy tour PUT is never used for published content edits.
    expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(false);

    await screen.findByText(/draft revision #4/);
    expect(screen.getByLabelText(englishForm.optionPrice)).toHaveValue(50);
  });

  it('routes a content-only general save on a published tour into the revision proposal', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Governed new title' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'POST' && c.url.endsWith('/api/partner/tours/42/revisions'))).toBe(true)
    );
    expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(false);
  });

  it('saves operational option changes only, keeping the approved identity and unsaved source text', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    // BOTH domains dirty: the operational save must still send only its own
    // domain and only the CURRENT APPROVED option text/ids, never the unsaved
    // edited option title or the unsaved source title.
    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Governed new title' } });
    fireEvent.change(screen.getByLabelText(englishForm.optionTitle), { target: { value: 'Edited option title' } });
    fireEvent.change(screen.getByLabelText(englishForm.optionPrice), { target: { value: '62.5' } });

    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveOperational }));

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(true)
    );
    const put = calls.find((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url));
    expect(Object.keys(put?.body as object)).toEqual(['options']);
    expect((put?.body as { options: Array<Record<string, unknown>> }).options).toEqual([
      {
        id: 5,
        title: 'Standard group',
        pickup_instructions: 'Hotel pickup',
        guide_languages: ['en'],
        price_amount: 6250,
        group_size_max: 10,
        start_time: null,
        weekdays: null,
      },
    ]);

    await screen.findByText(revisionEn.operationalSaved);
    // Every unsaved value survives the operational baseline refresh.
    expect(screen.getByDisplayValue('Governed new title')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Edited option title')).toBeInTheDocument();
    expect(screen.getByLabelText(englishForm.optionPrice)).toHaveValue(62.5);
    expect(calls.some((c) => c.url.includes('/revisions'))).toBe(false);
  });

  it('routes a commercial-only general save on a published tour into the operational save', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.change(screen.getByLabelText(englishForm.optionPrice), { target: { value: '55' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(true)
    );
    const put = calls.find((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url));
    expect(Object.keys(put?.body as object)).toEqual(['options']);
    expect(calls.some((c) => c.url.includes('/revisions'))).toBe(false);
  });

  it('restores the full published candidate payload instead of the opaque editor draft', async () => {
    const tourRef = {
      current: (publishedTour({ content_governance: publishedGovernance({ active_candidate: candidateMetadata('draft') }) }) as unknown) as Record<string, unknown>,
    };
    const calls = installRevisionApi(tourRef);
    await renderPage();

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'GET' && c.url.endsWith('/api/partner/tours/42/revisions/15'))).toBe(true)
    );
    expect(await screen.findByDisplayValue('Candidate title')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Candidate meeting point')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Candidate option title')).toBeInTheDocument();
    // Commercial values stay live while authored text comes from the candidate.
    expect(screen.getByLabelText(englishForm.optionPrice)).toHaveValue(50);

    // Published restore uses the revision, never the opaque draft button, and
    // published structure stays locked.
    expect(screen.queryByRole('button', { name: englishForm.restoreDraft })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: englishForm.addOption })).not.toBeInTheDocument();
    await screen.findByText(/Revision #4 loaded/);
  });

  it('restores authored option text when the revision snapshot stores options as an ID-keyed map', async () => {
    const orderedLiveOptions = [
      { ...liveOptions[0], id: 11, title: 'Live first option', price_amount: 7000 },
      { ...liveOptions[0], id: 5, title: 'Live second option', price_amount: 5000 },
    ];
    const tourRef = {
      current: (publishedTour({
        options: orderedLiveOptions,
        content_governance: publishedGovernance({ active_candidate: candidateMetadata('draft') }),
      }) as unknown) as Record<string, unknown>,
    };
    const detailWithMapOptions = {
      data: {
        ...revisionDetail('draft').data,
        payload: {
          ...candidatePayload,
          options: {
            '5': {
              title: 'Candidate second option from map',
              pickup_instructions: 'Second map pickup',
            },
            '11': {
              title: 'Candidate first option from map',
              pickup_instructions: 'First map pickup',
            },
          },
        },
      },
    };
    const calls = installRevisionApi(tourRef, (call) => {
      if (call.method === 'GET' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(call.url)) {
        return { status: 200, body: detailWithMapOptions };
      }
      return null;
    });
    await renderPage();

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'GET' && c.url.endsWith('/api/partner/tours/42/revisions/15'))).toBe(true)
    );
    expect(await screen.findByDisplayValue('Candidate title')).toBeInTheDocument();
    expect(await screen.findByDisplayValue('Candidate first option from map')).toBeInTheDocument();
    expect(screen.getByDisplayValue('First map pickup')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Candidate second option from map')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Second map pickup')).toBeInTheDocument();
    const titleInputs = screen.getAllByLabelText(englishForm.optionTitle);
    expect(titleInputs.map((input) => (input as HTMLInputElement).value)).toEqual([
      'Candidate first option from map',
      'Candidate second option from map',
    ]);
    const priceInputs = screen.getAllByLabelText(englishForm.optionPrice);
    expect(priceInputs.map((input) => (input as HTMLInputElement).value)).toEqual(['70', '50']);
  });

  it('replaces an open candidate through an immutable replacement that returns a NEW revision id', async () => {
    const tourRef = {
      current: (publishedTour({ content_governance: publishedGovernance({ active_candidate: candidateMetadata('draft') }) }) as unknown) as Record<string, unknown>,
    };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Candidate title');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Second candidate title' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));

    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && c.url.endsWith('/api/partner/tours/42/revisions/15'))).toBe(true)
    );
    const replaced = calls.find((c) => c.method === 'PUT' && c.url.endsWith('/api/partner/tours/42/revisions/15'));
    expect(replaced?.body).toMatchObject({
      base_revision_id: 12,
      observed_payload_hash: 'hash-15',
      translations: { en: { title: 'Second candidate title' } },
    });
    // The reviewed payload identity is carried only as the observed CAS token.
    expect(replaced?.body).not.toHaveProperty('id');
    expect(replaced?.body).not.toHaveProperty('payload_hash');

    await screen.findByText(/New revision #5/);
    expect(screen.getByText(/Replaced revision #4/)).toBeInTheDocument();
    expect(calls.filter((c) => c.method === 'POST' && c.url.endsWith('/api/partner/tours/42/revisions'))).toHaveLength(0);
  });

  it('retains every entered value when a revision replacement conflicts', async () => {
    const tourRef = {
      current: (publishedTour({ content_governance: publishedGovernance({ active_candidate: candidateMetadata('draft') }) }) as unknown) as Record<string, unknown>,
    };
    installRevisionApi(tourRef, (call) => {
      if (call.method === 'PUT' && call.url.endsWith('/api/partner/tours/42/revisions/15')) {
        return { status: 409, body: { message: 'payload hash mismatch', error_code: 'REVISION_CONFLICT', revision_id: 15 } };
      }
      return null;
    });
    await renderPage();
    await screen.findByDisplayValue('Candidate title');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Conflicting title' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));

    await screen.findByText(revisionEn.errors.conflict);
    expect(screen.getByDisplayValue('Conflicting title')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Candidate meeting point')).toBeInTheDocument();
  });

  it('fails closed with a retryable error when published governance metadata is missing', async () => {
    const tourRef = { current: publishedTour({ content_governance: undefined }) as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    expect(screen.getByText(revisionEn.errors.governanceMissing)).toBeInTheDocument();
    const callsBefore = calls.length;
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() => expect(calls.length).toBe(callsBefore));
    expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(false);
    expect(calls.some((c) => c.url.includes('/revisions'))).toBe(false);

    fireEvent.click(screen.getByRole('button', { name: revisionEn.errors.retry }));
    await waitFor(() =>
      expect(calls.filter((c) => c.method === 'GET' && c.url.includes('/api/partner/tours/42')).length).toBeGreaterThan(1)
    );
  });

  it('responds to a published change request with the exact submitted candidate', async () => {
    const tourRef = {
      current: publishedTour({
        content_governance: publishedGovernance({ active_candidate: candidateMetadata('proposed'), open_requests: [openRequest] }),
      }) as unknown as Record<string, unknown>,
    };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Candidate title');

    expect(screen.getByText('Please clarify the meeting point for travelers.')).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText(revisionEn.responseNote), { target: { value: 'Corrected.' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.respond }));

    await waitFor(() => {
      const respond = calls.find((c) => c.method === 'POST' && c.url.includes('/revision-requests/9/respond'));
      expect(respond).toBeDefined();
      expect(respond?.body).toEqual({ revision_id: 15, response_note: 'Corrected.' });
    });
    await screen.findByText(revisionEn.responded);
  });

  it('refuses a published response until a candidate is submitted for review', async () => {
    const tourRef = {
      current: (publishedTour({ content_governance: publishedGovernance({ open_requests: [openRequest] }) }) as unknown) as Record<string, unknown>,
    };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    expect(screen.getByText(revisionEn.respondNeedsSubmittedRevision)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: revisionEn.respond })).toBeDisabled();
    expect(calls.filter((c) => c.method === 'POST')).toHaveLength(0);
  });

  it('submits the loaded draft candidate and reflects the under-review state', async () => {
    const tourRef = {
      current: (publishedTour({ content_governance: publishedGovernance({ active_candidate: candidateMetadata('draft') }) }) as unknown) as Record<string, unknown>,
    };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Candidate title');

    fireEvent.click(screen.getByRole('button', { name: revisionEn.submit }));

    await screen.findByText(/Revision #4 submitted/);
    expect(calls.some((c) => c.method === 'POST' && c.url.endsWith('/api/partner/tours/42/revisions/15/submit'))).toBe(true);
    // Governance state refresh marks the candidate "Under review".
    expect(await screen.findByText(/Under review/)).toBeInTheDocument();
  });

  it('surfaces a rejected content revision with focused localized errors', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    installRevisionApi(tourRef, (call) => {
      if (call.method === 'POST' && call.url.endsWith('/api/partner/tours/42/revisions')) {
        return { status: 422, body: { message: 'Validation failed', errors: { 'translations.en.title': ['Title is required.'] } } };
      }
      return null;
    });
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));

    await screen.findByText(en.partner.tours.errors.titleRequired);
    const summary = screen.getByText(englishForm.errorSummary).closest('[role="alert"]');
    expect(summary).not.toBeNull();
    expect(document.activeElement).toBe(summary);
    // Values are retained: the edit is not silently reset onto the baseline.
    expect(screen.getByLabelText(/Title \(EN\)/)).toHaveValue('');
  });

  it('locks published option structure while keeping authored text and price editable', async () => {
    const tourRef = { current: publishedTour() as unknown as Record<string, unknown> };
    installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    expect(screen.queryByRole('button', { name: englishForm.addOption })).not.toBeInTheDocument();
    expect(screen.queryAllByRole('button', { name: /Remove option/i })).toHaveLength(0);
    expect(screen.getByText(englishForm.optionStructureLockedNotice)).toBeInTheDocument();
    expect(screen.getByLabelText(englishForm.optionTitle)).toBeEnabled();
    expect(screen.getByLabelText(englishForm.optionPrice)).toBeEnabled();
  });

  it('keeps the never-published draft save on the existing tour PUT with no revision calls', async () => {
    const tourRef = { current: ownedTour as unknown as Record<string, unknown> };
    const calls = installRevisionApi(tourRef);
    await renderPage();
    await screen.findByDisplayValue('Owned tour');

    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(true)
    );
    const put = calls.find((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url));
    expect(put?.body).toMatchObject({ translations: { en: { title: 'Owned tour' } } });
    expect(calls.filter((c) => c.url.includes('/revisions'))).toHaveLength(0);
  });

  it('uses reloaded candidate duration_minutes and change_summary as baselines and surfaces inline summary errors', async () => {
    const tourRef = {
      current: (publishedTour({
        content_governance: publishedGovernance({
          active_candidate: { ...candidateMetadata('draft'), change_summary: 'Extended tour duration to 4 hours' },
        }),
      }) as unknown) as Record<string, unknown>,
    };
    const detailWithModifiedDuration = {
      data: {
        ...revisionDetail('draft').data,
        change_summary: 'Extended tour duration to 4 hours',
        payload: {
          ...candidatePayload,
          presentation: {
            ...candidatePayload.presentation,
            duration_minutes: 240,
          },
        },
      },
    };
    let rejectNextReplace = false;
    const calls = installRevisionApi(tourRef, (call) => {
      if (call.method === 'GET' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(call.url)) {
        return { status: 200, body: detailWithModifiedDuration };
      }
      if (rejectNextReplace && call.method === 'PUT' && /\/api\/partner\/tours\/42\/revisions\/\d+$/.test(call.url)) {
        return {
          status: 422,
          body: {
            message: 'Validation failed',
            errors: { change_summary: ['Must not exceed 500 characters.'] },
          },
        };
      }
      return null;
    });

    await renderPage();
    expect(await screen.findByDisplayValue('Candidate title')).toBeInTheDocument();
    expect(screen.getByLabelText(revisionEn.changeSummary)).toHaveValue('Extended tour duration to 4 hours');
    expect(screen.getByLabelText(englishForm.durationValue)).toHaveValue(4);

    // Reloaded candidate has governedDirty=false: commercial price edits go straight to PUT /tours/42 without false mixed-save block.
    fireEvent.change(screen.getByLabelText(englishForm.optionPrice), { target: { value: '75' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save Tour Details' }));
    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42$/.test(c.url))).toBe(true)
    );
    expect(screen.queryByText(/Save them separately with/)).not.toBeInTheDocument();

    // Reverting duration back to live 3 hours (180 minutes) must send presentation.duration_minutes = 180.
    fireEvent.change(screen.getByLabelText(englishForm.durationValue), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));
    await waitFor(() =>
      expect(calls.some((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(c.url))).toBe(true)
    );
    const replaced = calls.find((c) => c.method === 'PUT' && /\/api\/partner\/tours\/42\/revisions\/15$/.test(c.url));
    expect(replaced?.body).toMatchObject({
      presentation: { duration_minutes: 180 },
      change_summary: 'Extended tour duration to 4 hours',
    });

    // Inline change_summary 422 error appears and clears on input edit.
    rejectNextReplace = true;
    fireEvent.change(screen.getByLabelText(/Title \(EN\)/), { target: { value: 'Next revision title' } });
    fireEvent.click(screen.getByRole('button', { name: revisionEn.saveContent }));
    await screen.findByText('Must not exceed 500 characters.');
    expect(screen.getByLabelText(revisionEn.changeSummary)).toHaveAttribute('aria-invalid', 'true');

    fireEvent.change(screen.getByLabelText(revisionEn.changeSummary), { target: { value: 'Updated valid summary' } });
    expect(screen.queryByText('Must not exceed 500 characters.')).not.toBeInTheDocument();
  });
});
