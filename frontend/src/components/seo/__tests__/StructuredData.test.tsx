import { render } from '@testing-library/react';
import { getSafeAbsoluteImageUrls, TouristTripSchema } from '../StructuredData';
import { formatCurrency } from '@/lib/utils';
import type { TourDetail } from '@/lib/api/types';

const tour: TourDetail = {
  id: 7,
  slug: 'guided-rome',
  title: 'Guided Rome',
  content_locale: 'en',
  description: 'Explore Rome.',
  location: 'Rome',
  category: { slug: 'walking', name: 'Walking' },
  duration: { minutes: 180, label: '3 hours' },
  languages: ['de', 'en', 'es'],
  guide_languages: ['de', 'en', 'es'],
  itinerary: [{ day: 1, title: 'English day', stops: [] }],
  itinerary_locale: 'en',
  group_size: { min: 1, max: 10 },
  cancellation_policy: '',
  highlights: [],
  inclusions: [],
  exclusions: [],
  meeting_point: 'Colosseum',
  images: [],
  pricing: { base_price: { amount: 5000, currency: 'EUR', formatted: '€50.00' }, tiered_pricing: null },
  availability: { next_available_date: null, available_dates: [], is_unavailable: true },
  rating: { average: 0, count: 0 },
  reviews: { average_rating: 0, count: 0, distribution: {} },
  seo: { meta_title: 'Guided Rome', meta_description: 'Explore Rome.', canonical_url: '', hreflang: {} },
  translation_warning: 'partial_translation',
};

it('marks English fallback content and itinerary as English on a Spanish page', () => {
  const { container } = render(<TouristTripSchema tour={tour} locale="es" />);
  const schema = JSON.parse(container.querySelector('script[type="application/ld+json"]')?.textContent ?? '{}');

  expect(schema['@context'].name['@language']).toBe('en');
  expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  expect(schema.inLanguage).toBeUndefined();
  expect(schema.url).toContain('/es/tours/guided-rome');
  expect(schema.itinerary['@context'].name['@language']).toBe('en');
  expect(schema.itinerary.inLanguage).toBeUndefined();
  expect(schema.itinerary.itemListElement[0].name).toBe('English day');
  expect(schema.aggregateRating).toBeUndefined();
});

it('uses the localized content language without changing guide metadata', () => {
  const { container } = render(<TouristTripSchema tour={{
    ...tour,
    title: 'Visita guidata di Roma',
    content_locale: 'it',
    itinerary: [{ day: 1, title: 'Giornata italiana', stops: [] }],
    itinerary_locale: 'it',
    translation_warning: undefined,
  }} locale="it" />);
  const schema = JSON.parse(container.querySelector('script[type="application/ld+json"]')?.textContent ?? '{}');

  expect(schema['@context'].name['@language']).toBe('it');
  expect(schema.mainEntityOfPage.inLanguage).toBe('it');
  expect(schema.itinerary['@context'].name['@language']).toBe('it');
  expect(schema.itinerary.itemListElement[0].name).toBe('Giornata italiana');
});

it('preserves itinerary order, all safe absolute gallery URLs and a truthful meeting point', () => {
  const { container } = render(<TouristTripSchema tour={{
    ...tour,
    itinerary: [
      { day: 2, title: 'Second day', stops: [] },
      { day: 4, title: 'Fourth day', stops: [] },
    ],
    images: [
      { url: '/images/cover.jpg', is_cover: true, alt: 'Cover' },
      { url: 'https://cdn.example.com/second.jpg', is_cover: false, alt: 'Gallery' },
      { url: 'javascript:alert(1)', is_cover: false, alt: 'Unsafe' },
    ],
  }} locale="en" />);
  const schema = JSON.parse(container.querySelector('script[type="application/ld+json"]')?.textContent ?? '{}');

  expect(schema.itinerary.itemListElement.map((item: { position: number; name: string }) => [item.position, item.name])).toEqual([
    [1, 'Second day'], [2, 'Fourth day'],
  ]);
  expect(schema.image).toEqual(['https://bookly.com/images/cover.jpg', 'https://cdn.example.com/second.jpg']);
  expect(schema.description).toContain('Colosseum');
});

it('omits unearned review and availability claims when the tour is unavailable', () => {
  const { container } = render(<TouristTripSchema tour={{
    ...tour,
    availability: { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: true },
    reviews: { average_rating: 0, count: 0, distribution: {} },
  }} locale="es" />);
  const schema = JSON.parse(container.querySelector('script[type="application/ld+json"]')?.textContent ?? '{}');

  expect(schema.aggregateRating).toBeUndefined();
  expect(schema.offers.availability).toBe('https://schema.org/OutOfStock');
  expect(schema['@context'].name['@language']).toBe('en');
  expect(schema.mainEntityOfPage.inLanguage).toBe('en');
  expect(schema.inLanguage).toBeUndefined();
});

it('does not allow traveler-facing content to break out of the JSON-LD script', () => {
  const malicious = '</script><script>alert(1)</script>';
  const { container } = render(<TouristTripSchema tour={{ ...tour, title: malicious }} locale="en" />);
  const script = container.querySelector('script[type="application/ld+json"]');
  expect(script?.innerHTML).not.toContain('</script>');
  expect(JSON.parse(script?.textContent ?? '{}').name).toBe(malicious);
});

it('represents the actual booked tour as a trip and service without unsupported duration or invented places', () => {
  const { container } = render(<TouristTripSchema tour={{
    ...tour,
    meeting_point: '  Authored gate  ',
    reviews: { average_rating: 4.5, count: 2, distribution: { 4: 1, 5: 1 } },
  }} locale="es" />);
  const schema = JSON.parse(container.querySelector('script')?.textContent ?? '{}');
  expect(schema['@type']).toEqual(['TouristTrip', 'Service']);
  expect(schema.duration).toBeUndefined();
  expect(schema.tripOrigin).toEqual({ '@type': 'Place', name: 'Authored gate' });
  expect(schema.aggregateRating).toMatchObject({ '@type': 'AggregateRating', ratingValue: '4.5', reviewCount: 2 });
  expect(schema.geo).toBeUndefined();
});

it('retains repeated authored day numbers and every ordered stop with the actual itinerary language', () => {
  const { container } = render(<TouristTripSchema tour={{
    ...tour,
    title: 'Titolo italiano',
    content_locale: 'it',
    itinerary_locale: 'en',
    itinerary: [
      { day: 7, title: 'First authored day', description: 'English description', stops: [
        { title: 'Walking activity', description: 'Walk with the guide', duration_minutes: 30 },
        { title: 'Rest break', description: null, duration_minutes: null },
      ] },
      { day: 7, title: 'Second authored day', description: null, stops: [] },
    ],
  }} locale="it" />);
  const schema = JSON.parse(container.querySelector('script')?.textContent ?? '{}');
  expect(schema['@context'].name['@language']).toBe('it');
  expect(schema.itinerary['@context'].name['@language']).toBe('en');
  const days = schema.itinerary.itemListElement;
  expect(days.map((day: { position: number; identifier: number }) => [day.position, day.identifier])).toEqual([[1, 7], [2, 7]]);
  expect(days[0].item.itemListElement.map((stop: { position: number; item: { name: string } }) => [stop.position, stop.item.name])).toEqual([
    [1, 'Walking activity'], [2, 'Rest break'],
  ]);
  expect(days[0].item.itemListElement[0].item).toEqual({ '@type': 'Thing', name: 'Walking activity', description: 'Walk with the guide' });
  expect(days[0].item.itemListElement[1].item.description).toBeUndefined();
  expect(days[1].item).toBeUndefined();
});

it('keeps unknown photos, itinerary, meeting point and genuine ratings absent', () => {
  const { container } = render(<TouristTripSchema tour={{ ...tour, images: [], itinerary: [], meeting_point: '   ' }} locale="en" />);
  const schema = JSON.parse(container.querySelector('script')?.textContent ?? '{}');
  expect(schema.image).toBeUndefined();
  expect(schema.itinerary).toBeUndefined();
  expect(schema.tripOrigin).toBeUndefined();
  expect(schema.aggregateRating).toBeUndefined();
});

// Keep offered-date assertions independent of the host UTC day.
const FROZEN_NOW = new Date('2026-10-14T00:00:00.000Z');

function frozenClock<T>(run: () => T): T {
  jest.useFakeTimers();
  jest.setSystemTime(FROZEN_NOW);
  try {
    return run();
  } finally {
    jest.useRealTimers();
  }
}

function readSchema(overrides: Partial<TourDetail>) {
  const { container } = render(<TouristTripSchema tour={{ ...tour, ...overrides }} locale="en" />);
  return JSON.parse(container.querySelector('script[type="application/ld+json"]')?.textContent ?? '{}');
}

function stripToDecimal(formatted: string): string {
  return formatted.replace(/[^0-9.,]/g, '').replace(/,/g, '');
}

describe('TouristTrip exact-price offer', () => {
  it('omits the offer for an unsafe integer cent amount instead of a false price', () => {
    const schema = readSchema({ pricing: { base_price: { amount: Number.MAX_SAFE_INTEGER + 1, currency: 'EUR', formatted: 'unused' }, tiered_pricing: null } });
    expect(schema.offers).toBeUndefined();
  });

  it('omits the offer for an unsupported currency instead of a converted price', () => {
    const schema = readSchema({ pricing: { base_price: { amount: 1099, currency: 'JPY', formatted: 'unused' }, tiered_pricing: null } });
    expect(schema.offers).toBeUndefined();
  });

  it.each([0, -500, 10.5, Number.NaN])('omits the offer for non-positive or fractional amount %s', (amount) => {
    const schema = readSchema({ pricing: { base_price: { amount, currency: 'EUR', formatted: 'unused' }, tiered_pricing: null } });
    expect(schema.offers).toBeUndefined();
  });

  it.each([
    { amount: Number.MAX_SAFE_INTEGER, expected: '90071992547409.91' },
    { amount: Number.MAX_SAFE_INTEGER - 9, expected: '90071992547409.82' },
  ])('renders $amount cents exactly as $expected, agreeing with the visible formatter', ({ amount, expected }) => {
    const schema = frozenClock(() => readSchema({
      pricing: { base_price: { amount, currency: 'EUR', formatted: 'unused' }, tiered_pricing: null },
      availability: { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false },
    }));
    expect(schema.offers.price).toBe(expected);
    expect(schema.offers.price).toBe(stripToDecimal(formatCurrency(amount, 'EUR', 'en')));
    expect(schema.offers.priceCurrency).toBe('EUR');
  });
});

describe('TouristTrip truthful availability', () => {
  it.each([
    { label: 'past', next: '2026-10-01', dates: ['2026-10-01', '2026-10-15'] },
    { label: 'today', next: '2026-10-14', dates: ['2026-10-14', '2026-10-15'] },
    { label: 'missing', next: null, dates: ['2026-10-15', '2026-10-16'] },
  ])('stays bookable with a $label next date and offered futures, without inventing validFrom', ({ next, dates }) => {
    const schema = frozenClock(() => readSchema({
      availability: { next_available_date: next, available_dates: dates, is_unavailable: false },
    }));
    expect(schema.offers.availability).toBe('https://schema.org/InStock');
    expect(schema.offers.validFrom).toBeUndefined();
  });

  it('marks only stale dates with no offered future OutOfStock without an invented validFrom', () => {
    const schema = frozenClock(() => readSchema({
      availability: { next_available_date: '2026-10-01', available_dates: ['2026-10-01'], is_unavailable: false },
    }));
    expect(schema.offers.availability).toBe('https://schema.org/OutOfStock');
    expect(schema.offers.validFrom).toBeUndefined();
  });

  it('fails closed when the unavailable flag is missing instead of claiming stock', () => {
    const availability = { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: undefined as unknown as boolean };
    const schema = frozenClock(() => readSchema({ availability }));
    expect(schema.offers.availability).toBe('https://schema.org/OutOfStock');
    expect(schema.offers.validFrom).toBeUndefined();
  });

  it('marks invalid group bounds OutOfStock without an invented validFrom', () => {
    const schema = frozenClock(() => readSchema({
      group_size: { min: 9, max: 8 },
      availability: { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false },
    }));
    expect(schema.offers.availability).toBe('https://schema.org/OutOfStock');
    expect(schema.offers.validFrom).toBeUndefined();
  });

  it('marks an unsafe minimum estimate OutOfStock without an invented validFrom', () => {
    const schema = frozenClock(() => readSchema({
      pricing: { base_price: { amount: Number.MAX_SAFE_INTEGER, currency: 'EUR', formatted: 'unused' }, tiered_pricing: null },
      group_size: { min: 2, max: 4 },
      availability: { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false },
    }));
    expect(schema.offers.availability).toBe('https://schema.org/OutOfStock');
    expect(schema.offers.validFrom).toBeUndefined();
  });
});

describe('TouristTrip genuine ratings and media', () => {
  it('omits aggregateRating for a fractional review count instead of a fabricated score', () => {
    const schema = readSchema({ reviews: { average_rating: 4.5, count: 2.5, distribution: { 4: 1, 5: 1 } } });
    expect(schema.aggregateRating).toBeUndefined();
  });

  it('omits blank image URLs instead of fabricating the homepage as a photo', () => {
    expect(getSafeAbsoluteImageUrls(
      [{ url: '' }, { url: '   ' }],
      'https://bookly.com',
    )).toEqual([]);
    const schema = readSchema({ images: [{ url: '', is_cover: true, alt: 'Blank' }, { url: '   ', is_cover: false, alt: 'Spaces' }] });
    expect(schema.image).toBeUndefined();
  });

  it('preserves legitimate relative/absolute URLs and dedupes them', () => {
    expect(getSafeAbsoluteImageUrls(
      [
        { url: '/images/cover.jpg' },
        { url: 'https://cdn.example.com/second.jpg' },
        { url: '/images/cover.jpg' },
        { url: 'javascript:alert(1)' },
      ],
      'https://bookly.com',
    )).toEqual(['https://bookly.com/images/cover.jpg', 'https://cdn.example.com/second.jpg']);
  });
});
