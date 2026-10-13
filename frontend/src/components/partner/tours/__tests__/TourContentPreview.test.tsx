import { render, screen, within } from '@testing-library/react';
import { NextIntlClientProvider } from 'next-intl';
import { TourContentPreview } from '../TourContentPreview';
import en from '../../../../../messages/en.json';
import es from '../../../../../messages/es.json';
import itMessages from '../../../../../messages/it.json';

jest.mock('next-intl', () => jest.requireActual('@/test-utils/catalogIntl'));

const catalogs = { en, es, it: itMessages };
type Locale = keyof typeof catalogs;

function expectNoDuplicateKeys(assertions: () => void) {
  const errors = jest.spyOn(console, 'error');
  try {
    assertions();
    expect(errors.mock.calls.filter((args) => /same key|unique.*key|two children/i.test(args.join(' ')))).toEqual([]);
  } finally {
    errors.mockRestore();
  }
}

function renderPreview(locale: Locale, props?: Partial<React.ComponentProps<typeof TourContentPreview>>) {
  return render(
    <NextIntlClientProvider locale={locale} messages={catalogs[locale]} timeZone="UTC">
      <TourContentPreview
        title="English source title"
        description="English source description."
        itinerary={[{ day: 1, title: 'Arrival day', description: null, stops: [] }]}
        media={[]}
        highlights={['English highlight']}
        inclusions={['English inclusion']}
        exclusions={['English exclusion']}
        important_information={['English info']}
        meeting_point="English meeting point"
        cancellation_policy="English cancellation policy"
        {...props}
      />
    </NextIntlClientProvider>
  );
}

describe('TourContentPreview (Spec 019 US2)', () => {
  it.each([
    ['en', 'Day 3'],
    ['es', 'Día 3'],
    ['it', 'Giorno 3'],
  ] as const)('renders actual repeated day values (%s) instead of list positions', (locale, dayLabel) => {
    expectNoDuplicateKeys(() => {
      const form = catalogs[locale].partner.tours.form;
      renderPreview(locale, {
        itinerary: [
          { day: 3, title: 'First third day', description: null, stops: [] },
          { day: 3, title: 'Second third day', description: null, stops: [] },
        ],
      });

      // Both valid days numbered 3 retain their authored values without
      // duplicate React keys or position-based Day 1/2 labels.
      const headings = screen.getAllByRole('heading', { name: new RegExp(`^${dayLabel}:`) });
      expect(headings).toHaveLength(2);
      expect(screen.getByRole('heading', { name: `${dayLabel}: First third day` })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: `${dayLabel}: Second third day` })).toBeInTheDocument();
      expect(screen.queryByText(`${form.dayNumber.replace('{number}', '1')}:`)).not.toBeInTheDocument();
    });
  });

  it('renders all nine English source fields with lang=en and page-locale labels', () => {
    const form = catalogs.en.partner.tours.form;
    renderPreview('en');

    // Page-locale headings stay unmarked; actual source text declares en.
    expect(screen.getByRole('heading', { name: form.highlights })).not.toHaveAttribute('lang');
    expect(screen.getByRole('heading', { name: 'English source title' })).toHaveAttribute('lang', 'en');
    for (const text of [
      'English source description.',
      'English highlight',
      'English inclusion',
      'English exclusion',
      'English info',
      'English meeting point',
      'English cancellation policy',
      'Arrival day',
    ]) {
      expect(screen.getByText(text).closest('[lang="en"]')).not.toBeNull();
    }
  });

  it('renders repeated list strings without duplicate string-only keys', () => {
    expectNoDuplicateKeys(() => {
      const { container } = renderPreview('en', {
        highlights: ['Same highlight', 'Same highlight'],
        inclusions: [],
        exclusions: [],
        important_information: [],
      });

      expect(screen.getAllByText('Same highlight')).toHaveLength(2);
      expect(container.querySelector('section')).not.toBeNull();
    });
  });

  it.each([
    ['en', 'pending', 'failed'],
    ['en', 'ready', 'stale'],
    ['en', 'stale', 'pending'],
    ['en', 'failed', 'ready'],
    ['es', 'pending', 'failed'],
    ['es', 'ready', 'stale'],
    ['es', 'stale', 'pending'],
    ['es', 'failed', 'ready'],
    ['it', 'pending', 'failed'],
    ['it', 'ready', 'stale'],
    ['it', 'stale', 'pending'],
    ['it', 'failed', 'ready'],
  ] as const)('renders %s saved readiness (ES %s, IT %s) with the revision note', (locale, esStatus, itStatus) => {
    const form = catalogs[locale].partner.tours.form;
    const { container } = renderPreview(locale, {
      translationStatuses: { es: esStatus, it: itStatus },
    });

    expect(screen.getByText(`ES: ${form.translationStatus[esStatus]}`)).toBeInTheDocument();
    expect(screen.getByText(`IT: ${form.translationStatus[itStatus]}`)).toBeInTheDocument();
    expect(screen.getByText(form.savedTranslations)).toBeInTheDocument();
    expect(screen.getByText(form.previewReadinessNote)).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'English source title' })).toHaveAttribute('lang', 'en');
    expect(container.textContent).not.toMatch(/source_hash|translated_hash|provider|job_state/i);
  });

  it.each(['en', 'es', 'it'] as const)('never fabricates stored derivative readiness for unsaved %s drafts', (locale) => {
    const form = catalogs[locale].partner.tours.form;
    renderPreview(locale, { translationStatuses: null });

    expect(screen.queryByText(form.savedTranslations)).not.toBeInTheDocument();
    expect(screen.queryByText(form.previewReadinessNote)).not.toBeInTheDocument();
    // Unsaved source preview still shows authored English content.
    expect(screen.getByRole('heading', { name: 'English source title' })).toBeInTheDocument();
  });

  it('keeps section scope for the empty-itinerary preview', () => {
    const form = catalogs.es.partner.tours.form;
    renderPreview('es', { itinerary: [] });

    expect(screen.queryByRole('heading', { name: form.itinerary })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'English source title' })).toBeInTheDocument();
    expect(within(screen.getByLabelText(form.previewTour)).getByText('English source description.')).toBeInTheDocument();
  });
});
