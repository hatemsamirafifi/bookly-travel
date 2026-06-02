import { render, screen } from '@testing-library/react';
import BookingCard from '../BookingCard';
import type { TravelerBooking } from '@/types/traveler';

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => <img {...props} />,
}));

const baseBooking: TravelerBooking = {
  id: '1',
  reference: 'BKO-ABC123',
  status: 'confirmed',
  tour: {
    id: 'tour-1',
    name: 'Rome Food Walk',
    slug: 'rome-food-walk',
    location: 'Rome, Italy',
  },
  tour_date: '2026-07-15',
  participants: 2,
  total_amount: 17800,
};

describe('BookingCard', () => {
  it('renders tour name, location, and date', () => {
    render(<BookingCard booking={baseBooking} locale="en" />);

    expect(screen.getByText('Rome Food Walk')).toBeInTheDocument();
    expect(screen.getByText('Rome, Italy')).toBeInTheDocument();
  });

  it('renders status badge with correct color', () => {
    render(<BookingCard booking={baseBooking} locale="en" />);

    const badge = screen.getByText('confirmed');
    expect(badge).toHaveClass('bg-green-100');
    expect(badge).toHaveClass('text-green-800');
  });

  it('renders cancelled status with red badge', () => {
    const cancelled = { ...baseBooking, status: 'cancelled' as const };
    render(<BookingCard booking={cancelled} locale="en" />);

    const badge = screen.getByText('cancelled');
    expect(badge).toHaveClass('bg-red-100');
  });

  it('renders completed status with blue badge', () => {
    const completed = { ...baseBooking, status: 'completed' as const };
    render(<BookingCard booking={completed} locale="en" />);

    const badge = screen.getByText('completed');
    expect(badge).toHaveClass('bg-blue-100');
  });

  it('links to booking detail page', () => {
    render(<BookingCard booking={baseBooking} locale="en" />);

    const link = screen.getByTestId('booking-card');
    expect(link).toHaveAttribute('href', '/en/my-bookings/BKO-ABC123');
  });

  it('renders participant count', () => {
    render(<BookingCard booking={baseBooking} locale="en" />);

    expect(screen.getByText(/2 participants/)).toBeInTheDocument();
  });

  it('renders cover image when available', () => {
    const withImage = {
      ...baseBooking,
      tour: { ...baseBooking.tour, cover_image: 'https://cdn.test/tour.jpg' },
    };
    render(<BookingCard booking={withImage} locale="en" />);

    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'https://cdn.test/tour.jpg');
  });

  it('renders price formatted from MoneyValue object', () => {
    const withMoneyValue = {
      ...baseBooking,
      total_amount: { amount: 17800, currency: 'EUR', formatted: '€178.00' },
      participants: undefined,
      participant_count: 3,
    };
    render(<BookingCard booking={withMoneyValue} locale="en" />);

    expect(screen.getByText('€178.00')).toBeInTheDocument();
    expect(screen.getByText(/3 participants/)).toBeInTheDocument();
  });

  it('falls back to tour title when name is absent', () => {
    const withTitle = {
      ...baseBooking,
      tour: { ...baseBooking.tour, name: '', title: 'Amazing Tour' },
    };
    render(<BookingCard booking={withTitle} locale="en" />);

    expect(screen.getByText('Amazing Tour')).toBeInTheDocument();
  });
});
