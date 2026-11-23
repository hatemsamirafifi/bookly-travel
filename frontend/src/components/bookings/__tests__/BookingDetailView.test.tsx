import { render, screen } from '@testing-library/react';
import BookingDetailView from '../BookingDetailView';
import type { TravelerBooking } from '@/types/traveler';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../messages/en.json';

const baseBooking: TravelerBooking = {
  id: '1',
  reference: 'BKO-DET-456',
  status: 'confirmed',
  tour: {
    id: 2,
    title: 'Historic Florence Walking Tour',
    slug: 'florence-walk',
    location: 'Florence, Italy',
  },
  tour_date: '2026-09-10',
  tour_time: '09:30',
  participants: 3,
  price_per_person: { amount: 4500, currency: 'EUR', formatted: '€45.00' },
  total_amount: { amount: 13500, currency: 'EUR', formatted: '€135.00' },
  departure: {
    id: 25,
    starts_at: '2026-09-10T09:30:00Z',
    timezone: 'Europe/Rome',
    local_date: '2026-09-10',
    local_time: '09:30:00',
  },
  pricing_snapshot: {
    mode: 'per_person',
    tier_id: 12,
    tier_name: 'Group 3+ Tier',
    unit_price_cents: 4500,
    total_price_cents: 13500,
    currency: 'EUR',
  },
};

function renderView(booking: TravelerBooking) {
  return render(
    <NextIntlClientProvider locale="en" messages={en} timeZone="UTC">
      <BookingDetailView booking={booking} locale="en" />
    </NextIntlClientProvider>
  );
}

describe('BookingDetailView', () => {
  it('renders departure date, time with timezone and tier snapshot name', () => {
    renderView(baseBooking);

    expect(screen.getByText('BKO-DET-456')).toBeInTheDocument();
    expect(screen.getByText('Historic Florence Walking Tour')).toBeInTheDocument();
    expect(screen.getByText('2026-09-10 · 09:30 (Europe/Rome)')).toBeInTheDocument();
    expect(screen.getByText('Group 3+ Tier')).toBeInTheDocument();
  });

  it('renders legacy booking fallback when departure is absent', () => {
    const legacy: TravelerBooking = {
      ...baseBooking,
      departure: null,
      pricing_snapshot: null,
    };
    renderView(legacy);

    expect(screen.getByText('2026-09-10 at 09:30')).toBeInTheDocument();
    expect(screen.queryByText('Group 3+ Tier')).not.toBeInTheDocument();
  });
});
