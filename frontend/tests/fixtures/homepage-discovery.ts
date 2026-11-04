import type {
  TourCard,
  Category,
  Destination,
  HomepageData,
  CuratedRail,
} from '../../src/lib/api/types';

export function buildMockTourCard(overrides: Partial<TourCard> = {}): TourCard {
  return {
    id: 101,
    slug: 'colosseum-underground-vip-tour',
    title: 'Colosseum Underground & Ancient Rome VIP Tour',
    location: 'Rome, Italy',
    category: 'Historical Tours',
    duration_label: '3.5 hours',
    price: {
      amount: 8900,
      currency: 'EUR',
      formatted: '€89',
    },
    rating: {
      average: 4.92,
      count: 128,
    },
    cover_image_url: 'https://cdn.bookly.test/tours/colosseum-cover.jpg',
    group_size: {
      min: 1,
      max: 12,
    },
    next_available_date: '2026-11-01',
    badge: 'featured',
    ...overrides,
  };
}

export function buildMockCategory(overrides: Partial<Category> = {}): Category {
  return {
    slug: 'walking-tours',
    name: 'Walking Tours',
    description: 'Explore vibrant city centers with expert local guides',
    image_url: 'https://cdn.bookly.test/categories/walking.jpg',
    tour_count: 24,
    ...overrides,
  };
}

export function buildMockDestination(overrides: Partial<Destination> = {}): Destination {
  return {
    slug: 'rome',
    name: 'Rome',
    country: 'Italy',
    image_url: 'https://cdn.bookly.test/destinations/rome.jpg',
    tour_count: 42,
    ...overrides,
  };
}

export function buildMockCuratedRail(overrides: Partial<CuratedRail> = {}): CuratedRail {
  return {
    id: 'family-friendly',
    slug: 'family-friendly',
    title: 'Top Family Experiences',
    tours: [
      buildMockTourCard({
        id: 105,
        slug: 'gladiator-school-for-kids',
        title: 'Gladiator School for Kids & Families',
        category: 'Family Friendly',
        price: { amount: 5500, currency: 'EUR', formatted: '€55' },
        badge: 'best-seller',
      }),
      buildMockTourCard({
        id: 106,
        slug: 'pizza-making-class-kids',
        title: 'Hands-on Pizza & Gelato Making for Families',
        category: 'Family Friendly',
        price: { amount: 6500, currency: 'EUR', formatted: '€65' },
        badge: 'new',
      }),
    ],
    ...overrides,
  };
}

export function buildCompleteHomepageFixture(): HomepageData {
  return {
    data: {
      featured_tours: [
        buildMockTourCard({ id: 101, title: 'Colosseum Underground Tour' }),
        buildMockTourCard({ id: 102, title: 'Vatican Museums & Sistine Chapel VIP' }),
        buildMockTourCard({ id: 103, title: 'Trastevere Sunset Food & Wine Tour' }),
      ],
      popular_categories: [
        buildMockCategory({ slug: 'walking-tours', name: 'Walking Tours', tour_count: 24 }),
        buildMockCategory({ slug: 'food-and-drink', name: 'Food & Drink', tour_count: 18 }),
        buildMockCategory({ slug: 'day-trips', name: 'Day Trips', tour_count: 15 }),
        buildMockCategory({ slug: 'outdoor-adventures', name: 'Outdoor Adventures', tour_count: 12 }),
      ],
      featured_destinations: [
        buildMockDestination({ slug: 'rome', name: 'Rome', country: 'Italy', tour_count: 42 }),
        buildMockDestination({ slug: 'florence', name: 'Florence', country: 'Italy', tour_count: 31 }),
        buildMockDestination({ slug: 'venice', name: 'Venice', country: 'Italy', tour_count: 27 }),
      ],
      curated_rails: [
        buildMockCuratedRail({ id: 'family-friendly', title: 'Top Family Experiences' }),
        buildMockCuratedRail({
          id: 'adventure-outdoor',
          slug: 'adventure-outdoor',
          title: 'Adventures & Outdoors',
          tours: [
            buildMockTourCard({ id: 201, title: 'Mount Vesuvius Crater Hike & Pompeii' }),
          ],
        }),
      ],
    },
    meta: {
      seo: {
        meta_title: 'Bookly - Discover & Book Amazing Tours',
        meta_description: 'Discover and instantly book the best tours worldwide.',
      },
    },
  };
}

export function buildEmptyCuratedRailsHomepageFixture(): HomepageData {
  const complete = buildCompleteHomepageFixture();
  return {
    ...complete,
    data: {
      ...complete.data,
      curated_rails: [],
    },
  };
}

