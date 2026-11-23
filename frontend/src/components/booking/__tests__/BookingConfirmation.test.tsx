import { render, screen, waitFor } from '@testing-library/react';
import BookingConfirmation from '../BookingConfirmation';
import type { TravelerBooking } from '@/types/traveler';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../messages/en.json';
import * as myBookingsApi from '@/lib/api/my-bookings';

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

jest.mock('@/lib/api/my-bookings', () => ({
  getBookingDetail: jest.fn(),
}));

const mockGetBookingDetail = myBookingsApi.getBookingDetail as jest.Mock;

const baseBooking: TravelerBooking = {
  id: '1',
  reference: 'BKO-TEST-123',
  status: 'confirmed',
  tour: {
    id: 1,
    name: 'Sunset Boat Tour',
    slug: 'sunset-boat-tour',
    location: 'Naples, Italy',
  },
  tour_date: '2026-08-20',
  participants: 2,
  total_amount: 12000,
  pricing: {
    price_per_person: { amount: 6000, currency: 'EUR', formatted: '€60.00' },
    total: { amount: 12000, currency: 'EUR', formatted: '€120.00' },
  },
  departure: {
    id: 10,
    starts_at: '2026-08-20T18:00:00Z',
    timezone: 'Europe/Rome',
    local_date: '2026-08-20',
    local_time: '18:00:00',
  },
  pricing_snapshot: {
    mode: 'per_person',
    tier_id: 5,
    tier_name: 'Couples Discount',
    unit_price_cents: 6000,
    total_price_cents: 12000,
    currency: 'EUR',
  },
};

function renderComponent(reference = 'BKO-TEST-123') {
  return render(
    <NextIntlClientProvider locale="en" messages={en} timeZone="UTC">
      <BookingConfirmation reference={reference} locale="en" />
    </NextIntlClientProvider>
  );
}

describe('BookingConfirmation', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders confirmed booking with departure time and rate tier snapshot', async () => {
    mockGetBookingDetail.mockResolvedValue({ data: baseBooking });

    renderComponent();

    await waitFor(() => {
      expect(screen.getByText('BKO-TEST-123')).toBeInTheDocument();
    });

    expect(screen.getByText('Sunset Boat Tour')).toBeInTheDocument();
    expect(screen.getByText('18:00 (Europe/Rome)')).toBeInTheDocument();
    expect(screen.getByText('Couples Discount')).toBeInTheDocument();
  });

  it('renders legacy booking gracefully when departure and tier snapshot are missing', async () => {
    const legacyBooking: TravelerBooking = {
      ...baseBooking,
      departure: null,
      pricing_snapshot: null,
    };
    mockGetBookingDetail.mockResolvedValue({ data: legacyBooking });

    renderComponent();

    await waitFor(() => {
      expect(screen.getByText('BKO-TEST-123')).toBeInTheDocument();
    });

    expect(screen.queryByText('18:00 (Europe/Rome)')).not.toBeInTheDocument();
    expect(screen.queryByText('Couples Discount')).not.toBeInTheDocument();
  });
});
