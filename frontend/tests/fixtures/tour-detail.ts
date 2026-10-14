import type {
  TourCard,
  TourDetail,
  TourImage,
  TourItineraryDay,
} from '../../src/lib/api/types';

/**
 * Immutable tour-detail fixtures.
 *
 * Base facts reuse the retained real public EN response
 * (`specs/020-tour-detail-experience/evidence/baseline/public-detail-en.json`:
 * Hidden Gems of Rome Walking Tour, EUR 4500, group 1-20, five genuine
 * gallery photos). Immutable here means each builder returns a fresh object
 * graph sharing no references with module state or caller-supplied overrides;
 * the returned tour itself stays mutable so state scenarios can exercise it.
 * Dates derive from an injected UTC clock so fixtures do not age.
 */

/** Fixed default clock (UTC midnight) matching the verified baseline day. */
export const FIXTURE_NOW_UTC = '2026-10-14T00:00:00.000Z';

export function toUtcDayString(date: Date): string {
  return date.toISOString().slice(0, 10);
}

function addUtcDays(base: Date, days: number): Date {
  return new Date(base.getTime() + days * 24 * 60 * 60 * 1000);
}

/**
 * Sixty consecutive offered UTC dates starting the day after `now`, mirroring
 * the real projection bound (at most 60 dates within 90 days). Starting
 * tomorrow keeps every offered date strictly after the injected UTC today.
 */
export function buildOfferedDates(now: Date, count = 60): string[] {
  const days: string[] = [];
  for (let offset = 1; offset <= count; offset += 1) {
    days.push(toUtcDayString(addUtcDays(now, offset)));
  }
  return days;
}

export type TourDetailOverrides = Partial<TourDetail>;

function baseImages(): TourImage[] {
  return [
    { url: 'http://cdn.bookly.test/tours/rome-cover.jpg', is_cover: true, alt: 'Hidden Gems of Rome Walking Tour', alt_locale: 'en' },
    { url: 'http://cdn.bookly.test/tours/rome-gallery-1.jpg', is_cover: false, alt: 'Hidden Gems of Rome Walking Tour', alt_locale: 'en' },
    { url: 'http://cdn.bookly.test/tours/rome-gallery-2.jpg', is_cover: false, alt: 'Hidden Gems of Rome Walking Tour', alt_locale: 'en' },
    { url: 'http://cdn.bookly.test/tours/rome-gallery-3.jpg', is_cover: false, alt: 'Hidden Gems of Rome Walking Tour', alt_locale: 'en' },
    { url: 'http://cdn.bookly.test/tours/rome-gallery-4.jpg', is_cover: false, alt: 'Hidden Gems of Rome Walking Tour', alt_locale: 'en' },
  ];
}

function baseItineraryDay(): TourItineraryDay[] {
  return [{ day: 1, title: 'Hidden courtyards walk', description: 'Walk the secret corners of Rome.', stops: [] }];
}

/** Fresh Rome base per call; dates derive from the injected clock. */
function createBaseTour(now: Date): TourDetail {
  const offered = buildOfferedDates(now);
  return {
    id: 1,
    slug: 'hidden-gems-rome-walking-tour',
    title: 'Hidden Gems of Rome Walking Tour',
    location: 'Rome, Italy',
    category: { slug: 'walking', name: 'Walking Tours' },
    rating: { average: 4.5, count: 2 },
    group_size: { min: 1, max: 20 },
    description:
      'Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through the Eternal City.',
    content_locale: 'en',
    highlights: ['Hidden courtyards', 'Local artisan shops', 'Ancient Roman stories', 'Off the beaten path'],
    inclusions: ['Expert local guide', 'Headset for clear commentary', 'Small group experience'],
    exclusions: ['Hotel pickup', 'Food and drinks', 'Gratuities'],
    important_information: [],
    meeting_point: 'Colosseum main entrance',
    cancellation_policy: 'Free cancellation up to 24 hours before the tour.',
    duration: { minutes: 180, label: '3 hours' },
    difficulty_level: null,
    languages: [],
    guide_languages: [],
    itinerary: [],
    itinerary_locale: 'en',
    images: baseImages(),
    pricing: {
      base_price: { amount: 4500, currency: 'EUR', formatted: '€45.00' },
      tiered_pricing: null,
    },
    availability: {
      next_available_date: offered[0] ?? null,
      available_dates: offered,
      is_unavailable: false,
    },
    reviews: { average_rating: 4.5, count: 2, distribution: { '5': 1, '4': 1, '3': 0, '2': 0, '1': 0 } },
    operator: null,
    related_tours: [],
    seo: {
      meta_title: 'Hidden Gems of Rome Walking Tour | Bookly',
      meta_description:
        'Discover the secret corners of Rome that most tourists never see. From hidden courtyards to local artisan shops, this tour takes you off the beaten path through',
      canonical_url: 'http://localhost/en/tours/hidden-gems-rome-walking-tour',
      hreflang: {
        en: 'http://localhost/en/tours/hidden-gems-rome-walking-tour',
        es: 'http://localhost/es/tours/hidden-gems-rome-walking-tour',
        it: 'http://localhost/it/tours/hidden-gems-rome-walking-tour',
      },
    },
    translation_status: 'source',
  };
}

/** Recursive copy for plain fixture data (jsdom exposes no structuredClone). */
function deepCopy<T>(value: T): T {
  if (Array.isArray(value)) return value.map(deepCopy) as T;
  if (value !== null && typeof value === 'object') {
    return Object.fromEntries(
      Object.entries(value).map(([key, entry]) => [key, deepCopy(entry)]),
    ) as T;
  }
  return value;
}

function applyOverrides(tour: TourDetail, overrides: TourDetailOverrides = {}): TourDetail {
  return { ...tour, ...deepCopy(overrides) };
}

function relatedCard(): TourCard {
  return {
    id: 2,
    slug: 'roman-forum-small-group',
    title: 'Roman Forum Small Group',
    location: 'Rome, Italy',
    category: 'Walking Tours',
    duration_label: '2 hours',
    price: { amount: 3900, currency: 'EUR', formatted: '€39.00' },
    rating: { average: 4.8, count: 11 },
    cover_image_url: 'http://cdn.bookly.test/tours/rome-gallery-1.jpg',
    group_size: { min: 1, max: 10 },
    next_available_date: null,
  };
}

/** Complete content: every optional section present (itinerary/operator/ related below are fixture-only additions; the base response ships them empty). */
export function createFullTourDetail(now = new Date(FIXTURE_NOW_UTC), overrides: TourDetailOverrides = {}): TourDetail {
  const tour = createBaseTour(now);
  tour.difficulty_level = 'moderate';
  tour.guide_languages = ['de', 'en', 'es'];
  tour.languages = ['de', 'en', 'es'];
  tour.itinerary = [
    { day: 1, title: 'Hidden courtyards walk', description: 'Walk the secret corners of Rome.', stops: [] },
    { day: 2, title: 'Artisan quarter', description: 'Meet local artisans.', stops: [] },
  ];
  tour.itinerary_locale = 'en';
  tour.important_information = ['Bring your passport'];
  tour.operator = {
    name: 'Rome Walks',
    description: 'Local guides.',
    logo_url: null,
    verified: true,
    tour_count: 3,
    review_count: 8,
    average_rating: 4.5,
  };
  tour.related_tours = [relatedCard()];
  return applyOverrides(tour, overrides);
}

/** Sparse content: only required overview/facts survive; reviews read zero. */
export function createSparseTourDetail(now = new Date(FIXTURE_NOW_UTC), overrides: TourDetailOverrides = {}): TourDetail {
  const tour = createBaseTour(now);
  tour.highlights = [];
  tour.inclusions = [];
  tour.exclusions = [];
  tour.important_information = [];
  tour.meeting_point = '';
  tour.cancellation_policy = '';
  tour.itinerary = [];
  tour.difficulty_level = null;
  tour.guide_languages = [];
  tour.languages = [];
  tour.images = [baseImages()[0]];
  tour.reviews = { average_rating: 0, count: 0, distribution: {} };
  tour.rating = { average: 0, count: 0 };
  tour.operator = null;
  tour.related_tours = [];
  return applyOverrides(tour, overrides);
}

/** Published but unbookable: no offered dates and the unavailable flag. */
export function createUnavailableTourDetail(now = new Date(FIXTURE_NOW_UTC), overrides: TourDetailOverrides = {}): TourDetail {
  const tour = createBaseTour(now);
  tour.availability = { next_available_date: null, available_dates: [], is_unavailable: true };
  return applyOverrides(tour, overrides);
}

/**
 * Fallback reading on an `es`/`it` page: general content and a nonempty
 * itinerary both remain English, so both notices apply independently.
 */
export function createFallbackTourDetail(
  pageLocale: 'es' | 'it',
  now = new Date(FIXTURE_NOW_UTC),
  overrides: TourDetailOverrides = {},
): TourDetail {
  void pageLocale;
  const tour = createBaseTour(now);
  tour.content_locale = 'en';
  tour.itinerary = baseItineraryDay();
  tour.itinerary_locale = 'en';
  tour.translation_warning = 'partial_translation';
  tour.translation_status = 'ready';
  return applyOverrides(tour, overrides);
}

/**
 * Intentionally empty translated itinerary: localized general content with
 * `itinerary: []` in the page locale. Must yield no itinerary section and no
 * fallback notice; no English substitution.
 */
export function createIntentionalEmptyItineraryTourDetail(
  pageLocale: 'es' | 'it',
  now = new Date(FIXTURE_NOW_UTC),
  overrides: TourDetailOverrides = {},
): TourDetail {
  const tour = createBaseTour(now);
  const localizedTitle = pageLocale === 'es' ? 'Rincones ocultos de Roma' : 'Angoli nascosti di Roma';
  tour.title = localizedTitle;
  tour.description = localizedTitle;
  tour.content_locale = pageLocale;
  tour.itinerary = [];
  tour.itinerary_locale = pageLocale;
  tour.translation_status = 'ready';
  return applyOverrides(tour, overrides);
}

/** Complete content with one broken gallery slot; count and order are preserved. */
export function createBrokenMediaTourDetail(now = new Date(FIXTURE_NOW_UTC), overrides: TourDetailOverrides = {}): TourDetail {
  const tour = createFullTourDetail(now);
  const images = baseImages();
  images[2] = { url: '', is_cover: false, alt: '', alt_locale: null };
  tour.images = images;
  return applyOverrides(tour, overrides);
}
