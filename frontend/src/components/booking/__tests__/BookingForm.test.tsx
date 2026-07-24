import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import BookingForm from '../BookingForm';
import type { TourDetail } from '@/lib/api/types';

jest.mock('@/lib/api/bookings', () => ({
  createBooking: jest.fn(),
}));

jest.mock('@/lib/api/tours', () => ({
  getTourDetail: jest.fn(),
}));

jest.mock('@/lib/api/my-bookings', () => ({
  cancelBooking: jest.fn(),
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
  useSearchParams: () => new URLSearchParams('tour=rome-food-walk&date=2026-08-01&participants=2'),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const translations: Record<string, string> = {
      title: 'Complete Your Booking',
      confirmationTitle: 'Booking Confirmed!',
      confirmButton: 'Confirm & Pay',
      confirming: 'Reserving...',
      selectPrompt: 'Please select a tour and date to continue.',
      paymentHeading: 'Payment',
      participants: 'Participants',
      total: 'Total',
      date: 'Selected Date',
      reference: 'Booking Reference',
      status: 'Status',
      tourLabel: 'Tour',
      'errors.loadFailed': 'Failed to load tour details.',
      'errors.soldOut': 'sold out',
      'errors.invalidDetails': 'invalid',
      'errors.rateLimit': 'rate limit',
      'errors.generic': 'Something went wrong.',
    };
    return translations[key] || key;
  },
}));

// Stub the composed child components so the test exercises BookingForm's own
// submit/idempotency logic in isolation.
jest.mock('../ParticipantSelector', () => function ParticipantSelector() {
  return <div data-testid="participant-selector" />;
});
jest.mock('../DateConfirmation', () => function DateConfirmation() {
  return <div data-testid="date-confirmation" />;
});
jest.mock('../PriceBreakdown', () => function PriceBreakdown() {
  return <div data-testid="price-breakdown" />;
});
jest.mock('../PriceChangeModal', () => function PriceChangeModal() {
  return null;
});
jest.mock('../StripePaymentForm', () => function StripePaymentForm() {
  return <div data-testid="stripe-form" />;
});
jest.mock('@/lib/stripe/stripe-client', () => ({ getStripe: () => Promise.resolve(null) }));

import { createBooking } from '@/lib/api/bookings';
import { getTourDetail } from '@/lib/api/tours';
import type { CreateBookingResult } from '@/lib/api/bookings';

const mockTourDetail = {
  data: {
    slug: 'rome-food-walk',
    location: 'Rome, Italy',
    duration_minutes: 180,
    duration_label: '3 hours',
    group_size: { min: 1, max: 8 },
    pricing: {
      base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' },
    },
  },
} as unknown as { data: TourDetail };

const successResult: CreateBookingResult = {
  data: {
    reference: 'BKO-TEST',
    tour: { slug: 'rome-food-walk', title: 'Rome Food Walk', location: 'Rome', cover_image_url: '' },
    tour_date: '2026-08-01',
    participant_count: 2,
    total_price: { amount: 17800, currency: 'EUR', formatted: '€178.00' },
    status: 'pending_payment',
    cancellation_policy: '',
    cancellation_window_hours: 24,
    can_cancel: true,
    created_at: '2026-01-01T00:00:00Z',
  },
  price_changed: false,
  payment: undefined,
};

describe('BookingForm', () => {
  beforeEach(() => {
    jest.mocked(getTourDetail).mockResolvedValue(mockTourDetail);
    jest.mocked(createBooking).mockResolvedValue(successResult);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  // F2: a synchronous submit guard prevents a double-click from issuing two
  // createBooking requests (which would otherwise race and create duplicates).
  it('submits only once when the confirm button is double-clicked', async () => {
    render(<BookingForm locale="en" />);

    const button = await screen.findByRole('button', { name: 'Confirm & Pay' });
    expect(button).not.toBeDisabled();

    fireEvent.click(button);
    fireEvent.click(button);

    await waitFor(() => {
      expect(createBooking).toHaveBeenCalledTimes(1);
    });
  });
});