import { render, screen } from '@testing-library/react';
import BookingCard from '../BookingCard';
import type { TravelerBooking } from '@/types/traveler';
import { NextIntlClientProvider } from 'next-intl';
import en from '../../../../messages/en.json';
import es from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

jest.mock('next-intl', () => {
  const React = jest.requireActual<typeof import('react')>('react');
  const Context = React.createContext<Record<string, unknown>>({});
  return {
    NextIntlClientProvider: ({ children, messages }: { children: React.ReactNode; messages: Record<string, unknown> }) =>
      React.createElement(Context.Provider, { value: messages }, children),
    useTranslations: (namespace: string) => {
      const tree = React.useContext(Context);
      return (key: string, values?: { count?: number }) => {
        const path = `${namespace}.${key}`;
        const value = path.split('.').reduce<unknown>((node, part) =>
          (node as Record<string, unknown>)?.[part], tree);
        if (typeof value !== 'string') return path;
        const plural = value.match(/^\{count, plural, one \{(.*?)\} other \{(.*?)\}\}$/);
        return plural
          ? plural[values?.count === 1 ? 1 : 2].replace('#', String(values?.count))
          : value;
      };
    },
  };
});

const messages = { en, es, it: itMessages };

function renderCard(booking: TravelerBooking, locale: keyof typeof messages = 'en') {
  return render(
    <NextIntlClientProvider locale={locale} messages={messages[locale]} timeZone="UTC">
      <BookingCard booking={booking} locale={locale} />
    </NextIntlClientProvider>,
  );
}

jest.mock('next/image', () => ({
  __esModule: true,
  default: (props: Record<string, unknown>) => (
    // eslint-disable-next-line @next/next/no-img-element, jsx-a11y/alt-text
    <img {...props} />
  ),
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
    renderCard(baseBooking);

    expect(screen.getByText('Rome Food Walk')).toBeInTheDocument();
    expect(screen.getByText('Rome, Italy')).toBeInTheDocument();
  });

  it('renders status badge with correct color', () => {
    renderCard(baseBooking);

    const badge = screen.getByText('Confirmed');
    expect(badge).toHaveClass('bg-green-100');
    expect(badge).toHaveClass('text-green-800');
  });

  it('renders cancelled status with red badge', () => {
    const cancelled = { ...baseBooking, status: 'cancelled' as const };
    renderCard(cancelled);

    const badge = screen.getByText('Cancelled');
    expect(badge).toHaveClass('bg-red-100');
  });

  it('renders completed status with blue badge', () => {
    const completed = { ...baseBooking, status: 'completed' as const };
    renderCard(completed);

    const badge = screen.getByText('Completed');
    expect(badge).toHaveClass('bg-blue-100');
  });

  it('links to booking detail page', () => {
    renderCard(baseBooking);

    const link = screen.getByTestId('booking-card');
    expect(link).toHaveAttribute('href', '/en/my-bookings/BKO-ABC123');
  });

  it('renders participant count', () => {
    renderCard(baseBooking);

    expect(screen.getByText(/2 participants/)).toBeInTheDocument();
  });

  it('renders cover image when available', () => {
    const withImage = {
      ...baseBooking,
      tour: { ...baseBooking.tour, cover_image: 'https://cdn.test/tour.jpg' },
    };
    renderCard(withImage);

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
    renderCard(withMoneyValue);

    expect(screen.getByText('€178.00')).toBeInTheDocument();
    expect(screen.getByText(/3 participants/)).toBeInTheDocument();
  });

  it('falls back to tour title when name is absent', () => {
    const withTitle = {
      ...baseBooking,
      tour: { ...baseBooking.tour, name: '', title: 'Amazing Tour' },
    };
    renderCard(withTitle);

    expect(screen.getByText('Amazing Tour')).toBeInTheDocument();
  });

  it.each([
    ['en', 'Confirmed', 'participants', 'participant'],
    ['es', 'Confirmada', 'participantes', 'participante'],
    ['it', 'Confermata', 'partecipanti', 'partecipante'],
  ] as const)('localizes card labels and dates in %s without changing the tour title', (locale, status, plural, singular) => {
    const { rerender } = renderCard(baseBooking, locale);
    expect(screen.getByText(status)).toBeInTheDocument();
    expect(screen.getByText(`2 ${plural}`)).toBeInTheDocument();
    expect(screen.getByText(new Date('2026-07-15T00:00:00').toLocaleDateString(locale))).toBeInTheDocument();
    expect(screen.getByText('Rome Food Walk')).toBeInTheDocument();

    rerender(
      <NextIntlClientProvider locale={locale} messages={messages[locale]} timeZone="UTC">
        <BookingCard booking={{ ...baseBooking, participants: 1 }} locale={locale} />
      </NextIntlClientProvider>,
    );
    expect(screen.getByText(`1 ${singular}`)).toBeInTheDocument();
  });
});
