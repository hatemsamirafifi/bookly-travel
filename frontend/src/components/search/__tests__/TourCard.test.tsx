import { render, screen } from '@testing-library/react';
import TourCard from '../TourCard';
import type { TourCard as TourCardType } from '@/lib/api/types';

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
  });
});
