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
