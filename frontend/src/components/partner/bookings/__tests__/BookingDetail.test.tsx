import { render, screen } from '@testing-library/react';
import { BookingDetail } from '../BookingDetail';
import type { PartnerBooking } from '@/types/partner';
import { NextIntlClientProvider } from 'next-intl';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import en from '../../../../../messages/en.json';

const queryClient = new QueryClient();

const basePartnerBooking: PartnerBooking = {
  id: 1,
  reference: 'BKO-PARTNER-789',
  status: 'confirmed',
  tour: {
    id: 10,
    title: 'Tuscan Vineyard Experience',
    slug: 'tuscan-vineyard',
  },
  traveler: {
    id: 99,
    name: 'Alice Traveler',
    email: 'alice@example.com',
  },
  booking_date: '2026-07-01T10:00:00Z',
  tour_date: '2026-10-15',
  participants: [
    {
      tier_id: 8,
      tier_name: 'Wine Lovers Special',
      count: 2,
      price_per_person: 75.0,
    },
  ],
  total_participants: 2,
  total_amount: 150.0,
  currency: 'EUR',
  payment_status: 'paid',
  created_at: '2026-07-01T10:00:00Z',
  updated_at: '2026-07-01T10:00:00Z',
  departure: {
    id: 101,
    starts_at: '2026-10-15T11:00:00Z',
    timezone: 'Europe/Rome',
    local_date: '2026-10-15',
    local_time: '11:00:00',
  },
  option: {
    id: 4,
    title: 'Cellar Tour & Tasting',
    pickup_instructions: 'Meet at main cellar gates',
  },
};

function renderPartnerBooking(booking: PartnerBooking) {
  return render(
    <QueryClientProvider client={queryClient}>
      <NextIntlClientProvider locale="en" messages={en} timeZone="UTC">
        <BookingDetail booking={booking} />
      </NextIntlClientProvider>
    </QueryClientProvider>
  );
}

describe('Partner BookingDetail', () => {
  it('renders departure time, option and tier breakdown', () => {
    renderPartnerBooking(basePartnerBooking);

    expect(screen.getByText('BKO-PARTNER-789')).toBeInTheDocument();
    expect(screen.getByText('Tuscan Vineyard Experience')).toBeInTheDocument();
    expect(screen.getByText('11:00 (Europe/Rome)')).toBeInTheDocument();
    expect(screen.getByText('Cellar Tour & Tasting')).toBeInTheDocument();
    expect(screen.getByText('Wine Lovers Special')).toBeInTheDocument();
  });

  it('renders legacy booking fallback when departure and option are absent', () => {
    const legacy: PartnerBooking = {
      ...basePartnerBooking,
      departure: null,
      option: null,
      tour_time: '11:00',
    };
    renderPartnerBooking(legacy);

    expect(screen.getByText('11:00')).toBeInTheDocument();
    expect(screen.queryByText('Cellar Tour & Tasting')).not.toBeInTheDocument();
  });
});
