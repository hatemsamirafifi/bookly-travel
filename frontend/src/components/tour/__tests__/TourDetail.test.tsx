import { render, screen } from '@testing-library/react';
import TourDetail from '../TourDetail';
import type { TourDetail as TourDetailType } from '@/lib/api/types';

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

const tour: TourDetailType = {
  id: 42,
  slug: 'tuscany-wine-tasting',
  title: 'Tuscany Wine Tasting',
  location: 'Florence, Italy',
  category: { slug: 'food-wine', name: 'Food & Wine' },
  duration_label: '5 hours',
  price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
  rating: { average: 4.7, count: 124 },
  cover_image_url: 'https://cdn.test/cover.jpg',
  group_size: { min: 2, max: 12 },
  next_available_date: '2026-07-15',
  description: 'Explore Tuscany.',
  highlights: ['Visit 3 wineries'],
  inclusions: ['Wine tasting'],
  exclusions: ['Gratuities'],
  meeting_point: 'Piazza della Repubblica',
  cancellation_policy: 'Free up to 24h.',
  duration: { minutes: 300, label: '5 hours' },
  languages: ['en', 'es'],
  images: [{ url: 'https://cdn.test/cover.jpg', is_cover: true, alt: 'Tuscany' }],
  pricing: {
    base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
    tiered_pricing: null,
  },
  availability: {
    next_available_date: '2026-07-15',
    available_dates: ['2026-07-15'],
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
  it('renders the category name, not the raw category object (F1 regression)', () => {
    render(<TourDetail tour={tour} locale="en" />);

    // The category object { slug, name } must not be rendered as a React child
    // (would throw "Objects are not valid as a React child"). Only the name.
    expect(screen.getByText('Food & Wine')).toBeInTheDocument();
    expect(screen.queryByText('food-wine')).not.toBeInTheDocument();
  });

  it('renders the title and location', () => {
    render(<TourDetail tour={tour} locale="en" />);

    expect(screen.getByRole('heading', { name: 'Tuscany Wine Tasting' })).toBeInTheDocument();
    expect(screen.getByText('Florence, Italy')).toBeInTheDocument();
  });

  it('renders the star rating from the rating object', () => {
    render(<TourDetail tour={tour} locale="en" />);

    expect(screen.getByLabelText('Rating: 4.7 out of 5')).toBeInTheDocument();
  });
});