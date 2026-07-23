import { render, screen } from '@testing-library/react';
import BookingCTA from '../BookingCTA';
import type { PricingInfo, AvailabilityInfo } from '@/lib/api/types';

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

jest.mock('@/components/wishlist/WishlistButton', () => ({
  __esModule: true,
  default: () => <button type="button">♡</button>,
}));

const price: PricingInfo = {
  base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
  tiered_pricing: null,
};

const groupSize = { min: 2, max: 12 };

describe('BookingCTA', () => {
  it('shows Book Now when available and not flagged unavailable', () => {
    const availability: AvailabilityInfo = {
      next_available_date: '2026-07-15',
      available_dates: ['2026-07-15'],
      is_unavailable: false,
    };

    render(
      <BookingCTA pricing={price} availability={availability} groupSize={groupSize} locale="en" slug="t" tourId={1} />
    );

    expect(screen.getByText('Book Now').closest('a')).toHaveAttribute('href', expect.stringContaining('/en/booking'));
  });

  it('shows Currently Unavailable when the backend flags is_unavailable, even with a next date (F2)', () => {
    // The mispriced-tour case: backend sets is_unavailable=true despite a
    // non-null next_available_date. The CTA must hide Book Now.
    const availability: AvailabilityInfo = {
      next_available_date: '2026-07-15',
      available_dates: ['2026-07-15'],
      is_unavailable: true,
    };

    render(
      <BookingCTA pricing={price} availability={availability} groupSize={groupSize} locale="en" slug="t" tourId={1} />
    );

    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
    // The Book Now button exists but must be disabled (no link).
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('shows Currently Unavailable when there are no available dates', () => {
    const availability: AvailabilityInfo = {
      next_available_date: null,
      available_dates: [],
    };

    render(
      <BookingCTA pricing={price} availability={availability} groupSize={groupSize} locale="en" slug="t" tourId={1} />
    );

    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
  });
});