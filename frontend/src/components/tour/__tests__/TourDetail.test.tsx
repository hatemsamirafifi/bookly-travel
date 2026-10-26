import { fireEvent, render, screen } from '@testing-library/react';
import TourDetail from '../TourDetail';
import type { TourDetail as TourDetailType } from '@/lib/api/types';
import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

const mockMessages = { en: enMessages, es: esMessages, it: itMessages };
let mockLocale: 'en' | 'es' | 'it' = 'en';
jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, string | number>) => {
    const root = mockMessages[mockLocale] as unknown as Record<string, unknown>;
    const scope = (namespace ? namespace.split('.').reduce<unknown>((node, part) => (node as Record<string, unknown> | undefined)?.[part], root) : root) as unknown as Record<string, string>;
    const value = scope?.[key] ?? key;
    return Object.entries(values ?? {}).reduce((result, [name, replacement]) => result.replace(`{${name}}`, String(replacement)), value);
  },
}));

// TourDetail composes several client components. For this unit test we only
// care that the header renders the category correctly (regression: F1 —
// `category` is an object, not a string, so it must NOT be rendered as a raw
// React child). Mock the children so we don't pull in next/image, next-intl,
// or the reviews fetch. The gallery mock captures the compositor contract
// (full image list, title, content locale) without the viewer engine.
jest.mock('../TourGallery', () => ({
  __esModule: true,
  default: ({
    images,
    title,
    contentLocale,
  }: {
    images: { url: string }[];
    title: string;
    contentLocale?: string;
  }) => (
    <div
      data-testid="tour-gallery"
      aria-label={title}
      data-count={images.length}
      data-urls={images.map((image) => image.url).join(',')}
      data-content-locale={contentLocale ?? ''}
    />
  ),
}));
jest.mock('../AvailabilityCalendar', () => ({
  __esModule: true,
  default: ({ nextAvailableDate }: { availableDates: string[]; nextAvailableDate: string | null }) => (
    <div data-testid="availability-calendar">{nextAvailableDate ?? 'none'}</div>
  ),
}));
jest.mock('@/components/reviews/ReviewList', () => ({
  __esModule: true,
  default: ({ tourSlug }: { tourSlug: string }) => (
    <div data-testid="review-list">{tourSlug}</div>
  ),
}));
jest.mock('../BookingCTA', () => ({
  __esModule: true,
  default: ({ slug }: { slug: string }) => (
    <div data-testid="booking-cta">{slug}</div>
  ),
}));
jest.mock('../TourBookingController', () => ({
  __esModule: true,
  default: ({
    slug,
    locale,
    todayUtc,
    cancellationPolicyLocale,
  }: {
    slug: string;
    locale: string;
    todayUtc: string;
    cancellationPolicyLocale?: string;
  }) => (
    <div data-testid="tour-booking-controller" data-slug={slug} data-locale={locale} data-today={todayUtc} data-policy-locale={cancellationPolicyLocale ?? ''} />
  ),
}));
jest.mock('../RelatedTours', () => ({
  __esModule: true,
  default: ({ tours }: { tours: { title: string }[] }) => <section id="related">{tours.map((related) => <span key={related.title}>{related.title}</span>)}</section>,
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: null }),
}));

const tour: TourDetailType = {
  id: 42,
  slug: 'tuscany-wine-tasting',
  title: 'Tuscany Wine Tasting',
  location: 'Florence, Italy',
  category: { slug: 'food-wine', name: 'Food & Wine' },
  rating: { average: 4.7, count: 124 },
  group_size: { min: 2, max: 12 },
  description: 'Explore Tuscany.',
  content_locale: 'en',
  highlights: ['Visit 3 wineries'],
  inclusions: ['Wine tasting'],
  exclusions: ['Gratuities'],
  meeting_point: 'Piazza della Repubblica',
  cancellation_policy: 'Free up to 24h.',
  duration: { minutes: 300, label: '5 hours' },
  languages: ['de', 'en', 'es'],
  guide_languages: ['de', 'en', 'es'],
  itinerary: [{ day: 1, title: 'Tuscan vineyards', description: 'Visit the vineyards.', stops: [] }],
  itinerary_locale: 'en',
  images: [{ url: 'https://cdn.test/cover.jpg', is_cover: true, alt: 'Tuscany' }],
  pricing: {
    base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
    tiered_pricing: null,
  },
  availability: {
    next_available_date: '2026-07-15',
    available_dates: ['2026-07-15'],
    is_unavailable: false,
  },
  reviews: { average_rating: 4.7, count: 124, distribution: { '5': 80 } },
  seo: {
    meta_title: 'Tuscany Wine Tasting | Bookly',
    meta_description: 'Explore Tuscany.',
    canonical_url: 'https://bookly.com/en/tours/tuscany-wine-tasting',
    hreflang: { en: 'https://bookly.com/en/tours/tuscany-wine-tasting' },
  },
};

describe('TourDetail', () => {
  const renderTour = (locale: 'en' | 'es' | 'it', value: TourDetailType = tour) => {
    mockLocale = locale;
    return render(<TourDetail tour={value} locale={locale} />);
  };

  it('renders the category name, not the raw category object (F1 regression)', () => {
    renderTour('en');

    // The category object { slug, name } must not be rendered as a React child
    // (would throw "Objects are not valid as a React child"). Only the name.
    expect(screen.getByText('Food & Wine')).toBeInTheDocument();
    expect(screen.queryByText('food-wine')).not.toBeInTheDocument();
  });

  it('renders the title and location', () => {
    renderTour('en');

    expect(screen.getByRole('heading', { name: 'Tuscany Wine Tasting' })).toBeInTheDocument();
    expect(screen.getByText('Florence, Italy')).toBeInTheDocument();
  });

  it('renders the star rating from the rating object', () => {
    renderTour('en');

    expect(screen.getByLabelText('Rating: 4.7 out of 5')).toBeInTheDocument();
  });

  it.each([
    ['en', 'Live guide:', 'German, English, Spanish', 'Day 1: Tuscan vineyards'],
    ['es', 'Guía en vivo:', 'Alemán, Inglés, Español', 'Día 1: Viñedos toscanos'],
    ['it', 'Guida dal vivo:', 'Tedesco, Inglese, Spagnolo', 'Giorno 1: Vigneti toscani'],
  ] as const)('renders guide metadata independently of %s content locale', (locale, label, languages, dayHeading) => {
    const title = locale === 'es' ? 'Cata de vinos en Toscana' : locale === 'it' ? 'Degustazione in Toscana' : tour.title;
    const dayTitle = locale === 'es' ? 'Viñedos toscanos' : locale === 'it' ? 'Vigneti toscani' : 'Tuscan vineyards';
    renderTour(locale, { ...tour, title, content_locale: locale, itinerary: [{ day: 1, title: dayTitle, stops: [] }], itinerary_locale: locale });

    expect(screen.getByText(label)).toBeInTheDocument();
    expect(screen.getByText(languages)).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: title })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: dayHeading })).toBeInTheDocument();
  });

  it('labels an English content fallback without changing guide languages', () => {
    renderTour('es', { ...tour, translation_warning: 'partial_translation' });

    // Spec 019 US2: full fallback discloses general content and the
    // nonempty English itinerary independently; guide codes stay unchanged.
    expect(screen.getByText(esMessages.tour.contentFallback)).toBeInTheDocument();
    expect(screen.getByText(esMessages.tour.itineraryFallback)).toBeInTheDocument();
    expect(screen.queryByText(esMessages.tour.partialTranslation)).not.toBeInTheDocument();
    expect(screen.getByText('Alemán, Inglés, Español')).toBeInTheDocument();
  });

  it.each([
    ['en', enMessages.tour.contentFallback, enMessages.tour.itineraryFallback],
    ['es', esMessages.tour.contentFallback, esMessages.tour.itineraryFallback],
    ['it', itMessages.tour.contentFallback, itMessages.tour.itineraryFallback],
  ] as const)('shows independent content/itinerary fallback notices in %s', (locale, contentNotice, itineraryNotice) => {
    // Current requested general content with a null itinerary falling back
    // to nonempty English: only the itinerary notice appears.
    renderTour(locale, {
      ...tour,
      title: locale === 'en' ? tour.title : 'Contenido localizado',
      content_locale: locale,
      itinerary: [{ day: 1, title: 'English day', stops: [] }],
      itinerary_locale: 'en',
      translation_status: locale === 'en' ? 'source' : 'ready',
      translation_warning: locale === 'en' ? undefined : 'partial_translation',
    });

    if (locale === 'en') {
      expect(screen.queryByText(contentNotice)).not.toBeInTheDocument();
      expect(screen.queryByText(itineraryNotice)).not.toBeInTheDocument();
    } else {
      expect(screen.queryByText(contentNotice)).not.toBeInTheDocument();
      expect(screen.getByText(itineraryNotice)).toBeInTheDocument();
    }
  });

  it('suppresses the itinerary section and notice for an empty itinerary', () => {
    renderTour('es', {
      ...tour,
      content_locale: 'es',
      itinerary: [],
      itinerary_locale: 'es',
      translation_status: 'ready',
    });

    expect(screen.queryByRole('heading', { name: esMessages.tour.itinerary })).not.toBeInTheDocument();
    expect(screen.queryByText(esMessages.tour.itineraryFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(esMessages.tour.contentFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(esMessages.tour.partialTranslation)).not.toBeInTheDocument();
  });

  // Root correction R2 (red first): the optional API summary flag alone
  // must never produce a UI notice for empty sections or requested-language
  // content. Notices derive from actual locales AND nonempty sections only.
  it.each([
    ['en', enMessages.tour],
    ['es', esMessages.tour],
    ['it', itMessages.tour],
  ] as const)('shows no fallback notice for empty sections in %s even with the summary flag', (locale, messages) => {
    renderTour(locale, {
      ...tour,
      title: '',
      description: '',
      highlights: [],
      inclusions: [],
      exclusions: [],
      meeting_point: '',
      cancellation_policy: '',
      important_information: [],
      content_locale: locale,
      itinerary: [],
      itinerary_locale: locale,
      translation_warning: 'partial_translation',
    });

    expect(screen.queryByText(messages.contentFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(messages.itineraryFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(messages.partialTranslation)).not.toBeInTheDocument();
  });

  it.each([
    ['en', enMessages.tour],
    ['es', esMessages.tour],
    ['it', itMessages.tour],
  ] as const)('shows no fallback notice for current requested content in %s even with the summary flag', (locale, messages) => {
    renderTour(locale, {
      ...tour,
      content_locale: locale,
      itinerary: [{ day: 1, title: 'Localized day', stops: [] }],
      itinerary_locale: locale,
      translation_warning: 'partial_translation',
    });

    expect(screen.queryByText(messages.contentFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(messages.itineraryFallback)).not.toBeInTheDocument();
    expect(screen.queryByText(messages.partialTranslation)).not.toBeInTheDocument();
  });

  it('declares actual text languages while keeping page-locale labels', () => {
    const { container } = renderTour('es', {
      ...tour,
      content_locale: 'es',
      itinerary: [{ day: 1, title: 'Día español', stops: [] }],
      itinerary_locale: 'es',
    });

    expect(screen.getByRole('heading', { level: 1 })).toHaveAttribute('lang', 'es');
    // Root correction R3: the breadcrumb carries the same authored title,
    // so its source span also declares the actual content language.
    expect(screen.getByText('Tuscany Wine Tasting', { selector: 'span' })).toHaveAttribute('lang', 'es');
    const itineraryHeading = screen.getByRole('heading', { name: 'Día 1: Día español' });
    expect(itineraryHeading.querySelector('span')).toHaveAttribute('lang', 'es');
    // Page-locale section labels carry no content lang override.
    expect(screen.getByRole('heading', { name: esMessages.tour.itinerary })).not.toHaveAttribute('lang');
    expect(container.querySelector('#itinerary')).not.toBeNull();
  });

  it('renders repeated valid day numbers with stable composite keys', () => {
    const errors = jest.spyOn(console, 'error');
    try {
      // React duplicate-key regression: two valid days numbered 3 must both
      // render their actual day value, not their list position.
      const repeated = {
        ...tour,
        itinerary: [
          { day: 3, title: 'First third day', stops: [] },
          { day: 3, title: 'Second third day', stops: [] },
        ],
        itinerary_locale: 'en' as const,
      };
      const { container } = renderTour('en', repeated);

      const headings = screen.getAllByRole('heading', { name: /Day 3:/ });
      expect(headings).toHaveLength(2);
      expect(screen.getByRole('heading', { name: 'Day 3: First third day' })).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: 'Day 3: Second third day' })).toBeInTheDocument();
      expect(container.querySelectorAll('#itinerary li').length).toBeGreaterThanOrEqual(2);
      expect(errors.mock.calls.filter((args) => /same key|unique.*key|two children/i.test(args.join(' ')))).toEqual([]);
    } finally {
      errors.mockRestore();
    }
  });

  it.each([
    ['en', enMessages.tour.importantInformation],
    ['es', esMessages.tour.importantInformation],
    ['it', itMessages.tour.importantInformation],
  ] as const)('renders translated important information in %s', (locale, heading) => {
    renderTour(locale, {
      ...tour,
      important_information: [locale === 'es' ? 'Trae tu pasaporte' : locale === 'it' ? 'Porta il passaporto' : 'Bring your passport'],
      content_locale: locale,
      translation_status: locale === 'en' ? 'source' : 'ready',
    });

    expect(screen.getByRole('heading', { name: heading })).toBeInTheDocument();
  });

  it('omits empty optional sections while retaining the booking action', () => {
    renderTour('en', {
      ...tour,
      highlights: [], inclusions: [], exclusions: [], itinerary: [],
      meeting_point: '', cancellation_policy: '', important_information: [],
    });

    expect(screen.queryByRole('heading', { name: enMessages.tour.itinerary })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: enMessages.tour.highlights })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: enMessages.tour.included })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: enMessages.tour.meetingPoint })).not.toBeInTheDocument();
    expect(screen.getByTestId('tour-booking-controller')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: enMessages.tour.itinerary })).not.toBeInTheDocument();
  });

  it('navigates only to visible sections with a safe sticky offset and renders public operator and related tours', () => {
    const { container } = renderTour('en', {
      ...tour,
      difficulty_level: 'moderate',
      operator: { name: 'Tuscan Walks', description: 'Local guides.', logo_url: null, tour_count: 3, review_count: 8, average_rating: 4.5 },
      related_tours: [{
        id: 43, slug: 'other-tour', title: 'Other tour', location: 'Florence, Italy',
        category: 'Food & Wine', duration_label: '2 hours',
        price: { amount: 5000, currency: 'EUR', formatted: '€50.00' },
        rating: { average: 0, count: 0 }, cover_image_url: '',
        group_size: { min: 1, max: 8 }, next_available_date: null,
      }],
    });

    expect(screen.getByRole('link', { name: enMessages.tour.itinerary })).toHaveAttribute('href', '#itinerary');
    expect(screen.getByRole('navigation', { name: enMessages.tour.sectionNavigation })).toHaveClass('sticky');
    expect(container.querySelector('#itinerary')).toHaveClass('scroll-mt-20');
    expect(screen.getByText('Tuscan Walks')).toBeInTheDocument();
    expect(screen.getByText('Other tour')).toBeInTheDocument();
    expect(screen.getByText('Local guides.')).toBeInTheDocument();
  });

  it('scopes navigation and present-section anchors under the tour-detail marker', () => {
    const { container } = renderTour('en');

    // The marker is the single coordination point: the nav publishes its
    // measured offset on this container and the sidebar inherits it, while
    // the scroll-margin rules apply only to sections inside it.
    const marker = container.querySelector('[data-tour-detail]');
    expect(marker).not.toBeNull();
    const nav = screen.getByRole('navigation', { name: enMessages.tour.sectionNavigation });
    expect(marker).toContainElement(nav);

    // Every supplied nav entry resolves to a present section in the marker.
    const hrefs = Array.from(nav.querySelectorAll('a')).map((link) => link.getAttribute('href'));
    expect(hrefs.length).toBeGreaterThan(0);
    for (const href of hrefs) {
      expect(href).toMatch(/^#/);
      expect(marker!.querySelector(href!)).not.toBeNull();
    }
  });

  describe('operator and related (Spec 019 US5)', () => {
    const verifiedOperator = {
      name: 'Tuscan Walks',
      description: 'Local guides.',
      logo_url: null as string | null,
      verified: true,
      tour_count: 3,
      review_count: 8,
      average_rating: 4.5,
    };

    it('renders the verification badge only for verified operators', () => {
      // RED-first (T060): T063 wires the localized verified label through
      // OperatorSummary; the badge is asserted by test id so the test stays
      // green regardless of the final localized copy.
      const { container, unmount } = renderTour('en', { ...tour, operator: verifiedOperator, related_tours: [] });
      expect(container.querySelector('#operator')).not.toBeNull();
      expect(screen.getByTestId('operator-verified')).toBeInTheDocument();
      unmount();

      renderTour('en', {
        ...tour,
        operator: { ...verifiedOperator, verified: false },
        related_tours: [],
      });
      expect(screen.queryByTestId('operator-verified')).not.toBeInTheDocument();
    });

    it('omits the operator section safely when the summary is absent', () => {
      const { container } = renderTour('en', {
        ...tour,
        related_tours: [{
          id: 43, slug: 'other-tour', title: 'Other tour', location: 'Florence, Italy',
          category: 'Food & Wine', duration_label: '2 hours',
          price: { amount: 5000, currency: 'EUR', formatted: '€50.00' },
          rating: { average: 0, count: 0 }, cover_image_url: '',
          group_size: { min: 1, max: 8 }, next_available_date: null,
        }],
      });

      expect(container.querySelector('#operator')).toBeNull();
      expect(screen.queryByRole('heading', { name: enMessages.tour.operator })).not.toBeInTheDocument();
      // Related existing cards still render unchanged.
      expect(screen.getByText('Other tour')).toBeInTheDocument();
    });

    it('shows a zero-review operator without invented rating claims', () => {
      const { container } = renderTour('en', {
        ...tour,
        operator: { ...verifiedOperator, review_count: 0, average_rating: null },
        related_tours: [],
      });

      expect(container.querySelector('#operator')).not.toBeNull();
      expect(screen.getByText('Tuscan Walks')).toBeInTheDocument();
      expect(container.querySelector('#operator')?.textContent).not.toMatch(/null|NaN|undefined/);
    });

    it('renders existing related cards without inventing aggregate claims', () => {
      renderTour('en', {
        ...tour,
        operator: undefined,
        related_tours: [
          {
            id: 43, slug: 'rated-tour', title: 'Rated tour', location: 'Florence, Italy',
            category: 'Food & Wine', duration_label: '2 hours',
            price: { amount: 5000, currency: 'EUR', formatted: '€50.00' },
            rating: { average: 4.5, count: 8 }, cover_image_url: '',
            group_size: { min: 1, max: 8 }, next_available_date: '2026-07-15',
          },
          {
            id: 44, slug: 'unrated-tour', title: 'Unrated tour', location: 'Rome, Italy',
            category: 'Food & Wine', duration_label: '3 hours',
            price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
            rating: { average: 0, count: 0 }, cover_image_url: '',
            group_size: { min: 1, max: 8 }, next_available_date: null,
          },
        ],
      });

      // The composed related list renders existing card titles as-is; no
      // aggregate text is fabricated at the detail level.
      expect(screen.getByText('Rated tour')).toBeInTheDocument();
      expect(screen.getByText('Unrated tour')).toBeInTheDocument();
      expect(screen.queryByText(/\(0 reviews\)/)).not.toBeInTheDocument();
    });
  });

  describe('US1 readable hierarchy (Spec 020)', () => {
    const completeTour: TourDetailType = {
      ...tour,
      description: 'Explore Tuscany in depth.',
      difficulty_level: 'moderate',
      important_information: ['Bring your passport for the cellar check.'],
      itinerary: [
        { day: 1, title: 'Tuscan vineyards', description: 'Visit the vineyards.', stops: [] },
        { day: 2, title: 'Cellar lunch', description: 'Taste with the maker.', stops: [] },
      ],
      operator: { name: 'Tuscan Walks', description: 'Local guides.', logo_url: null, tour_count: 3, review_count: 8, average_rating: 4.5 },
      related_tours: [{
        id: 43, slug: 'other-tour', title: 'Other tour', location: 'Florence, Italy',
        category: 'Food & Wine', duration_label: '2 hours',
        price: { amount: 5000, currency: 'EUR', formatted: '€50.00' },
        rating: { average: 0, count: 0 }, cover_image_url: '',
        group_size: { min: 1, max: 8 }, next_available_date: null,
      }],
    };

    const assertDocumentOrder = (elements: (Element | null)[]) => {
      elements.forEach((element) => expect(element).not.toBeNull());
      for (let index = 0; index < elements.length - 1; index += 1) {
        expect(elements[index]!.compareDocumentPosition(elements[index + 1]!)).toBe(
          Node.DOCUMENT_POSITION_FOLLOWING,
        );
      }
    };

    it('orders header, gallery, nav, article sections and related with no orphan entries', () => {
      const { container } = renderTour('en', completeTour);

      const gallery = container.querySelector('[data-testid="tour-gallery"]');
      const nav = screen.getByRole('navigation', { name: enMessages.tour.sectionNavigation });
      assertDocumentOrder([
        screen.getByRole('heading', { level: 1 }),
        gallery,
        nav,
        container.querySelector('#overview'),
        container.querySelector('#facts'),
        container.querySelector('#highlights'),
        container.querySelector('#itinerary'),
        container.querySelector('#included'),
        container.querySelector('#excluded'),
        container.querySelector('#meeting'),
        container.querySelector('#cancellation'),
        container.querySelector('#important'),
        container.querySelector('#operator'),
        container.querySelector('#reviews'),
        container.querySelector('#related'),
      ]);

      // Navigation targets exactly the rendered sections: no orphans, no gaps.
      const renderedIds = ['overview', 'facts', 'highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related'];
      expect(Array.from(nav.querySelectorAll('a')).map((link) => link.getAttribute('href'))).toEqual(
        renderedIds.map((id) => `#${id}`),
      );
      for (const id of renderedIds) {
        expect(container.querySelectorAll(`#${id}`)).toHaveLength(1);
      }
    });

    it('keeps only overview and facts for sparse content with matching navigation', () => {
      const { container } = renderTour('en', {
        ...tour,
        highlights: [], inclusions: [], exclusions: [], itinerary: [],
        meeting_point: '', cancellation_policy: '', important_information: [],
        operator: null, related_tours: [],
        reviews: { average_rating: 0, count: 0, distribution: {} },
        rating: { average: 0, count: 0 },
      });

      expect(container.querySelector('#overview')).not.toBeNull();
      expect(container.querySelector('#facts')).not.toBeNull();
      for (const id of ['highlights', 'itinerary', 'included', 'excluded', 'meeting', 'cancellation', 'important', 'operator', 'reviews', 'related']) {
        expect(container.querySelector(`#${id}`)).toBeNull();
      }
      const nav = screen.getByRole('navigation', { name: enMessages.tour.sectionNavigation });
      expect(Array.from(nav.querySelectorAll('a')).map((link) => link.getAttribute('href'))).toEqual([
        '#overview', '#facts',
      ]);
      expect(screen.getByTestId('tour-booking-controller')).toBeInTheDocument();
    });

    it('keeps important information fully readable without preview controls', () => {
      renderTour('en', {
        ...completeTour,
        important_information: [`Passport rule. ${'Carry it always. '.repeat(60)}`],
      });

      expect(screen.getByText(/Passport rule\./)).toBeInTheDocument();
      expect(screen.getByText(/Carry it always\./)).toBeInTheDocument();
      const important = document.querySelector('#important');
      expect(important?.querySelector('button')).toBeNull();
    });

    it('exposes long descriptions through an accessible disclosure', () => {
      const longDescription = `${'Tuscan sun. '.repeat(80)}Final reachable sentence.`;
      renderTour('en', { ...tour, description: longDescription });

      expect(screen.queryByText(longDescription)).not.toBeInTheDocument();
      const expand = screen.getByRole('button', { name: enMessages.tour.overview.readMore });
      expect(expand).toHaveAttribute('aria-expanded', 'false');
      expect(expand.getAttribute('aria-controls')).toBeTruthy();

      fireEvent.click(expand);
      expect(screen.getByText(longDescription)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: enMessages.tour.overview.readLess })).toHaveAttribute(
        'aria-expanded',
        'true',
      );
    });

    it('keeps short descriptions fully readable with no disclosure', () => {
      renderTour('en', completeTour);

      expect(screen.getByText('Explore Tuscany in depth.')).toBeInTheDocument();
      const overview = document.querySelector('#overview');
      expect(overview?.querySelector('button')).toBeNull();
    });
  });

  describe('US2 booking boundary (Spec 020)', () => {
    it('mounts one shared booking controller with the tour identity, locale and server UTC day', () => {
      const { container } = renderTour('en', tour);

      const controller = screen.getByTestId('tour-booking-controller');
      expect(controller).toHaveAttribute('data-slug', 'tuscany-wine-tasting');
      expect(controller).toHaveAttribute('data-locale', 'en');
      expect(controller.getAttribute('data-today')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      // The controller owns the sidebar booking slot; legacy engines are gone.
      expect(screen.queryByTestId('booking-cta')).not.toBeInTheDocument();
      expect(container.querySelector('aside [data-testid="availability-calendar"]')).toBeNull();
    });

    it('passes an injected server UTC snapshot through instead of the client clock', () => {
      renderTour('es', { ...tour, slug: 'other-slug' });

      const controller = screen.getByTestId('tour-booking-controller');
      expect(controller).toHaveAttribute('data-slug', 'other-slug');
      expect(controller).toHaveAttribute('data-locale', 'es');
      expect(controller.getAttribute('data-today')).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    });

    it('keeps the server-rendered article alongside the booking boundary', () => {
      const { container } = renderTour('en', tour);

      expect(screen.getByRole('heading', { name: 'Tuscany Wine Tasting' })).toBeInTheDocument();
      expect(container.querySelector('#overview')).not.toBeNull();
      expect(container.querySelector('#facts')).not.toBeNull();
      expect(screen.getByTestId('tour-booking-controller')).toBeInTheDocument();
    });
  });

  describe('US6 language disclosure (Spec 020)', () => {
    const englishFallbackTour: TourDetailType = {
      ...tour,
      title: 'Tuscany Wine Tasting',
      description: 'Explore Tuscany.',
      content_locale: 'en',
      itinerary: [{ day: 1, title: 'Tuscan vineyards', stops: [] }],
      itinerary_locale: 'en',
      translation_status: 'pending',
      translation_warning: 'partial_translation',
    };

    it.each([
      ['es', esMessages.tour.contentFallback, esMessages.tour.itineraryFallback],
      ['it', itMessages.tour.contentFallback, itMessages.tour.itineraryFallback],
    ] as const)('shows paired fallback notices in one shared gap layout in %s', (locale, contentNotice, itineraryNotice) => {
      renderTour(locale, englishFallbackTour);

      const contentElement = screen.getByText(contentNotice);
      const itineraryElement = screen.getByText(itineraryNotice);
      // Paired notices share one wrapping element that separates them with
      // layout gap instead of offset margins on the second notice.
      expect(contentElement.parentElement).toBe(itineraryElement.parentElement);
      expect(contentElement.parentElement).toHaveClass('gap-2');
      expect(itineraryElement).not.toHaveClass('ml-2');
    });

    it.each([
      ['es', esMessages.tour],
      ['it', itMessages.tour],
    ] as const)('shows no fallback notice for whitespace-only English content in %s', (locale, messages) => {
      renderTour(locale, {
        ...tour,
        title: '   ',
        description: ' \n\t ',
        highlights: ['  '],
        inclusions: [],
        exclusions: [null as unknown as string],
        meeting_point: '   ',
        cancellation_policy: '',
        important_information: ['   '],
        content_locale: 'en',
        itinerary: [{ day: 1, title: '   ', stops: [] }],
        itinerary_locale: 'en',
        translation_warning: 'partial_translation',
      });

      expect(screen.queryByText(messages.contentFallback)).not.toBeInTheDocument();
      expect(screen.queryByText(messages.itineraryFallback)).not.toBeInTheDocument();
      expect(screen.queryByText(messages.partialTranslation)).not.toBeInTheDocument();
      expect(screen.queryByRole('heading', { name: messages.itinerary })).not.toBeInTheDocument();
    });

    it.each([
      ['es', esMessages.tour],
      ['it', itMessages.tour],
    ] as const)('keeps an intentionally empty translated itinerary empty with no substitution in %s', (locale, messages) => {
      renderTour(locale, {
        ...tour,
        title: locale === 'es' ? 'Cata localizada' : 'Degustazione localizzata',
        description: locale === 'es' ? 'Descripción localizada.' : 'Descrizione localizzata.',
        content_locale: locale,
        itinerary: [],
        itinerary_locale: locale,
        translation_status: 'ready',
        translation_warning: undefined,
      });

      expect(screen.queryByRole('heading', { name: messages.itinerary })).not.toBeInTheDocument();
      expect(screen.queryByText(messages.itineraryFallback)).not.toBeInTheDocument();
      expect(screen.queryByText(messages.contentFallback)).not.toBeInTheDocument();
      expect(screen.queryByText('Tuscan vineyards')).not.toBeInTheDocument();
    });

    it('preserves full multiline practical text with line breaks', () => {
      renderTour('en', {
        ...tour,
        meeting_point: 'Gate A\nSecond line at the gate',
        cancellation_policy: 'Free up to 24h.\nRefunds take five days.',
        important_information: ['Bring your passport\nKeep it with you'],
      });

      expect(screen.getByText(/Gate A/)).toBeInTheDocument();
      expect(screen.getByText(/Second line at the gate/)).toBeInTheDocument();
      expect(screen.getByText(/Refunds take five days\./)).toBeInTheDocument();
      expect(screen.getByText(/Keep it with you/)).toBeInTheDocument();
      const meeting = document.querySelector('#meeting p');
      expect(meeting).toHaveClass('whitespace-pre-line');
      const cancellation = document.querySelector('#cancellation p');
      expect(cancellation).toHaveClass('whitespace-pre-line');
    });

    it.each([
      ['es', esMessages.tour.contentFallback, esMessages.tour.itineraryFallback],
      ['it', itMessages.tour.contentFallback, itMessages.tour.itineraryFallback],
    ] as const)('declares English on fallback text while notices stay in %s page locale', (locale, contentNotice, itineraryNotice) => {
      renderTour(locale, englishFallbackTour);

      expect(screen.getByRole('heading', { level: 1 })).toHaveAttribute('lang', 'en');
      expect(screen.getByText(contentNotice)).not.toHaveAttribute('lang');
      expect(screen.getByText(itineraryNotice)).not.toHaveAttribute('lang');
      const itineraryHeading = screen.getByRole('heading', { name: /Tuscan vineyards/ });
      expect(itineraryHeading.querySelector('span')).toHaveAttribute('lang', 'en');
    });

    it.each([
      ['es', 'Guía en vivo:', 'Alemán, Inglés, Español'],
      ['it', 'Guida dal vivo:', 'Tedesco, Inglese, Spagnolo'],
    ] as const)('localizes spoken guide names in %s during English content fallback', (locale, label, languages) => {
      renderTour(locale as 'es' | 'it', englishFallbackTour);

      expect(screen.getByText(label)).toBeInTheDocument();
      expect(screen.getByText(languages)).toBeInTheDocument();
    });
  });

  describe('US3 canonical gallery composition (Spec 020)', () => {
    const galleryPhoto = (n: number, over: Partial<TourDetailType['images'][number]> = {}) => ({
      url: `https://cdn.test/gallery-${n}.jpg`,
      is_cover: n === 1,
      alt: `Gallery photo ${n}`,
      ...over,
    });

    it.each([0, 1, 4, 7])('composes the canonical gallery with the full %s-photo list, title and content locale', (count) => {
      const images = Array.from({ length: count }, (_, index) => galleryPhoto(index + 1));
      renderTour('en', { ...tour, title: 'Gallery pass-through', content_locale: 'en', images });

      // The legacy engine is gone: only the canonical gallery slot renders.
      expect(screen.queryByTestId('image-gallery')).not.toBeInTheDocument();
      const gallery = screen.getByTestId('tour-gallery');
      expect(gallery).toHaveAttribute('aria-label', 'Gallery pass-through');
      expect(gallery).toHaveAttribute('data-count', String(count));
      expect(gallery).toHaveAttribute('data-content-locale', 'en');
    });

    it('keeps caller order in the composed image list', () => {
      const images = [
        galleryPhoto(2, { is_cover: false }),
        galleryPhoto(1, { is_cover: true }),
        galleryPhoto(3, { is_cover: false }),
      ];
      renderTour('en', { ...tour, images });

      // The compositor passes the model order through untouched (cover-first
      // sorting stays the gallery's job); nothing is dropped or re-sorted.
      expect(screen.getByTestId('tour-gallery')).toHaveAttribute(
        'data-urls',
        'https://cdn.test/gallery-2.jpg,https://cdn.test/gallery-1.jpg,https://cdn.test/gallery-3.jpg',
      );
    });

    it('passes the content locale for the cancellation policy while page controls stay localized', () => {
      // ES page, EN content: the policy keeps its actual language while
      // section labels follow the page locale.
      renderTour('es', {
        ...tour,
        content_locale: 'en',
        cancellation_policy: 'Free up to 24h.\nRefunds take five days.',
      });

      const controller = screen.getByTestId('tour-booking-controller');
      expect(controller).toHaveAttribute('data-policy-locale', 'en');
      expect(controller).toHaveAttribute('data-locale', 'es');
      expect(screen.getByText(/Refunds take five days\./)).toBeInTheDocument();
      expect(screen.getByRole('heading', { name: esMessages.tour.cancellationPolicy })).toBeInTheDocument();
    });
  });

  describe('US5 genuine reviews and header trust (Spec 020)', () => {
    it('uses the authoritative reviews summary for the header score, not the card rating', () => {
      renderTour('en', {
        ...tour,
        rating: { average: 5, count: 200 },
        reviews: { average_rating: 4.0, count: 10, distribution: { '4': 10 } },
      });

      // A stale/inflated card rating must never surface as the header score.
      expect(screen.queryByLabelText('Rating: 5 out of 5')).not.toBeInTheDocument();
      expect(screen.getByLabelText('Rating: 4 out of 5')).toBeInTheDocument();
      expect(screen.getByRole('link', { name: '(10 Reviews)' })).toHaveAttribute('href', '#reviews');
    });

    it('shows no header score, section or nav entry when reviews are zero despite a card rating', () => {
      const { container } = renderTour('en', {
        ...tour,
        rating: { average: 5, count: 200 },
        reviews: { average_rating: 0, count: 0, distribution: {} },
      });

      expect(screen.queryByLabelText(/out of 5/)).not.toBeInTheDocument();
      expect(container.querySelector('#reviews')).toBeNull();
      const nav = screen.getByRole('navigation', { name: enMessages.tour.sectionNavigation });
      expect(Array.from(nav.querySelectorAll('a')).map((link) => link.getAttribute('href'))).not.toContain('#reviews');
    });

    it('allows real review browsing without fabricated stars when the average is unavailable', () => {
      const { container } = renderTour('en', {
        ...tour,
        rating: { average: 5, count: 200 },
        reviews: { average_rating: 0, count: 10, distribution: {} },
      });

      // Browsing stays (section, nav, review link) but no scored badge appears.
      expect(container.querySelector('#reviews')).not.toBeNull();
      expect(screen.getByRole('link', { name: '(10 Reviews)' })).toHaveAttribute('href', '#reviews');
      expect(screen.queryByLabelText(/out of 5/)).not.toBeInTheDocument();
    });

    it('renders the reviews section through the TourReviews wrapper with a real localized heading', () => {
      const { container } = renderTour('en');

      const section = container.querySelector('#reviews');
      expect(section?.tagName).toBe('SECTION');
      expect(screen.getByRole('heading', { level: 2, name: 'Reviews' })).toBeInTheDocument();
      expect(section).toContainElement(screen.getByTestId('review-list'));
    });

    it('exposes exactly one current-tour wishlist control in the header', () => {
      const { container } = renderTour('en');

      const currentButtons = container.querySelectorAll('[data-testid="wishlist-button"][data-tour-id="42"]');
      expect(currentButtons).toHaveLength(1);
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    });
  });
});
