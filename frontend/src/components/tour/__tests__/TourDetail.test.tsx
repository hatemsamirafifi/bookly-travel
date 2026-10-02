import { render, screen } from '@testing-library/react';
import TourDetail from '../TourDetail';
import type { TourDetail as TourDetailType } from '@/lib/api/types';
import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

const mockMessages = { en: enMessages, es: esMessages, it: itMessages };
let mockLocale: 'en' | 'es' | 'it' = 'en';
jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: Record<string, string | number>) => {
    const value = (mockMessages[mockLocale].tour as unknown as Record<string, string>)[key] ?? key;
    return Object.entries(values ?? {}).reduce((result, [name, replacement]) => result.replace(`{${name}}`, String(replacement)), value);
  },
}));

// TourDetail composes several client components. For this unit test we only
// care that the header renders the category correctly (regression: F1 —
// `category` is an object, not a string, so it must NOT be rendered as a raw
// React child). Mock the children so we don't pull in next/image, next-intl,
// or the reviews fetch.
jest.mock('../ImageGallery', () => ({
  __esModule: true,
  default: ({ title }: { images: unknown[]; title: string }) => (
    <div data-testid="image-gallery" aria-label={title} />
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
jest.mock('../RelatedTours', () => ({
  __esModule: true,
  default: ({ tours }: { tours: { title: string }[] }) => <div>{tours.map((related) => <span key={related.title}>{related.title}</span>)}</div>,
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

    expect(screen.getByText(esMessages.tour.partialTranslation)).toBeInTheDocument();
    expect(screen.getByText('Alemán, Inglés, Español')).toBeInTheDocument();
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
    expect(screen.getByTestId('booking-cta')).toBeInTheDocument();
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
});
