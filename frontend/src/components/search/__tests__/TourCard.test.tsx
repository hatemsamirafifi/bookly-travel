import { render, screen } from '@testing-library/react';
import TourCard from '../TourCard';
import type { TourCard as TourCardType } from '@/lib/api/types';

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: { date?: string; count?: number }) => {
    if (key === 'nextAvailable') return `Next: ${values?.date}`;
    if (key === 'reviewCount_one') return `${values?.count} review`;
    if (key === 'reviewCount') return `${values?.count} reviews`;
    return key;
  },
}));

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => {
    const { fill, ...rest } = props;
    // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
    return <img {...rest} data-fill={fill ? 'true' : undefined} />;
  },
}));

jest.mock('@/components/wishlist/WishlistButton', () => ({
  __esModule: true,
  default: ({ tourId, compact }: { tourId: number; compact: boolean }) => (
    <button data-testid="wishlist-button" data-tour-id={tourId} data-compact={compact}>
      ♡
    </button>
  ),
}));

jest.mock('@/lib/images', () => ({
  getImagePlaceholderProps: () => ({}),
}));

const tour: TourCardType = {
  id: 42,
  slug: 'florence-food-walk',
  title: 'Florence Food Walk',
  location: 'Florence, Italy',
  category: 'Food & Wine',
  duration_label: '3 hours',
  price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
  rating: { average: 4.5, count: 128 },
  cover_image_url: 'https://cdn.test/tour-cover.jpg',
  group_size: { min: 1, max: 10 },
  next_available_date: '2026-07-15',
};

describe('TourCard', () => {
  it('eagerly loads an explicitly prioritized cover without changing its URL', () => {
    render(<TourCard tour={tour} locale="en" imagePriority />);
    const image = screen.getByRole('img', { name: tour.title });
    expect(image).toHaveAttribute('loading', 'eager');
    expect(image).toHaveAttribute('fetchpriority', 'high');
    expect(image).toHaveAttribute('src', tour.cover_image_url);
  });

  it('does not prioritize ordinary listing covers', () => {
    render(<TourCard tour={tour} locale="en" />);
    const image = screen.getByRole('img', { name: tour.title });
    expect(image).not.toHaveAttribute('loading', 'eager');
    expect(image).not.toHaveAttribute('fetchpriority', 'high');
  });

  it('renders tour title, location, and duration', () => {
    render(<TourCard tour={tour} locale="en" />);

    expect(screen.getByText('Florence Food Walk')).toBeInTheDocument();
    expect(screen.getByText('Florence, Italy')).toBeInTheDocument();
    expect(screen.getByText('3 hours')).toBeInTheDocument();
  });

  it('renders price and category', () => {
    render(<TourCard tour={tour} locale="en" />);

    expect(screen.getByText('€89.00')).toBeInTheDocument();
    expect(screen.getByText('Food & Wine')).toBeInTheDocument();
  });

  it('renders star rating', () => {
    render(<TourCard tour={tour} locale="en" />);

    expect(screen.getByText('(128 reviews)')).toBeInTheDocument();
    expect(screen.getByLabelText('Rating: 4.5 out of 5')).toBeInTheDocument();
  });

  it('links to tour detail page', () => {
    render(<TourCard tour={tour} locale="en" />);

    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/en/tours/florence-food-walk');
  });

  it('renders next available date when present', () => {
    render(<TourCard tour={tour} locale="en" />);

    expect(screen.getByText(/Next:/)).toBeInTheDocument();
  });

  it('omits next available date when null', () => {
    const withoutDate = { ...tour, next_available_date: null };
    render(<TourCard tour={withoutDate} locale="en" />);

    expect(screen.queryByText(/Next:/)).not.toBeInTheDocument();
  });

  it('renders fallback placeholder when cover image is missing', () => {
    const withoutImage = { ...tour, cover_image_url: '' };
    render(<TourCard tour={withoutImage} locale="en" />);

    expect(screen.getByText('Florence Food Walk')).toBeInTheDocument();
  });

  it('renders wishlist button', () => {
    render(<TourCard tour={tour} locale="en" />);

    const btn = screen.getByTestId('wishlist-button');
    expect(btn).toBeInTheDocument();
    expect(btn).toHaveAttribute('data-tour-id', '42');
    expect(btn).toHaveAttribute('data-compact', 'true');
    expect(btn.closest('a')).toBeNull();
  });

  it('does not present an invented rating when no reviews exist', () => {
    render(<TourCard tour={{ ...tour, rating: { average: 0, count: 0 } }} locale="en" />);
    expect(screen.queryByLabelText(/Rating:/)).not.toBeInTheDocument();
    expect(screen.queryByText(/reviews/)).not.toBeInTheDocument();
  });
});
