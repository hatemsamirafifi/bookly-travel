import { render } from '@testing-library/react';
import { TouristTripSchema } from '../StructuredData';
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
