import { render, screen } from '@testing-library/react';
import BookingCTA from '../BookingCTA';
import type { PricingInfo, AvailabilityInfo } from '@/lib/api/types';
import enMessages from '../../../../messages/en.json';
import esMessages from '../../../../messages/es.json';
import itMessages from '../../../../messages/it.json';

// Spec 020 US2 (T029): BookingCTA is a thin compatibility wrapper over the
// canonical TourBookingController. These tests preserve the meaningful legacy
// unavailable/deep-link regressions through the shared selection engine.

let mockLocale: 'en' | 'es' | 'it' = 'en';
const messages = { en: enMessages, es: esMessages, it: itMessages };
jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, string | number>) => {
    const root = messages[mockLocale] as unknown as Record<string, unknown>;
    const scope = (namespace
      ? namespace.split('.').reduce<unknown>(
          (node, part) => (node as Record<string, unknown> | undefined)?.[part],
          root,
        )
      : root) as unknown as Record<string, unknown>;
    const message =
      (key.split('.').reduce<unknown>(
        (node, part) => (node as Record<string, unknown> | undefined)?.[part],
        scope,
      ) as string | undefined) ?? key;
    return Object.entries(values ?? {}).reduce(
      (text, [name, value]) => text.replace(`{${name}}`, String(value)),
      message,
    );
  },
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  ),
}));

const mockUseAuth = jest.fn();
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockUseAuth(),
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
const TODAY_UTC = '2026-10-14';

function renderCta(availability: AvailabilityInfo, locale: 'en' | 'es' | 'it' = 'en') {
  mockLocale = locale;
  return render(
    <BookingCTA
      pricing={price}
      availability={availability}
      groupSize={groupSize}
      locale={locale}
      slug="t"
      tourId={1}
      todayUtc={TODAY_UTC}
    />,
  );
}

describe('BookingCTA', () => {
  beforeEach(() => {
    mockLocale = 'en';
    mockUseAuth.mockReturnValue({ user: { id: 7 }, token: 'token', isLoading: false });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('shows Book Now when available and not flagged unavailable', () => {
    const availability: AvailabilityInfo = {
      next_available_date: '2026-10-15',
      available_dates: ['2026-10-15'],
      is_unavailable: false,
    };

    renderCta(availability);

    expect(screen.getByText('Book Now').closest('a')).toHaveAttribute('href', expect.stringContaining('/en/booking'));
  });

  it('shows Currently Unavailable when the backend flags is_unavailable, even with a next date (F2)', () => {
    // The mispriced-tour case: backend sets is_unavailable=true despite a
    // non-null next_available_date. The CTA must hide Book Now.
    const availability: AvailabilityInfo = {
      next_available_date: '2026-10-15',
      available_dates: ['2026-10-15'],
      is_unavailable: true,
    };

    renderCta(availability);

    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
    // The Book Now button exists but must be disabled (no link).
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('shows Currently Unavailable when there are no available dates', () => {
    const availability: AvailabilityInfo = {
      next_available_date: null,
      available_dates: [],
      is_unavailable: true,
    };

    renderCta(availability);

    expect(screen.getByText('Currently Unavailable')).toBeInTheDocument();
  });

  it.each(['es', 'it'] as const)('localizes booking controls for %s', (locale) => {
    renderCta(
      {
        next_available_date: '2026-10-15',
        available_dates: ['2026-10-15'],
        is_unavailable: false,
      },
      locale,
    );
    expect(screen.getByRole('link', { name: messages[locale].tour.bookNow })).toHaveAttribute('href', expect.stringContaining(`/${locale}/booking`));
    expect(screen.getByText(messages[locale].tour.participants)).toBeInTheDocument();
  });

  it('does not offer booking when the advertised date is absent from inventory or the price is invalid', () => {
    const { rerender } = render(
      <BookingCTA
        pricing={price}
        availability={{ next_available_date: '2026-10-15', available_dates: ['2026-10-16'], is_unavailable: false }}
        groupSize={groupSize}
        locale="en"
        slug="t"
        tourId={1}
        todayUtc={TODAY_UTC}
      />,
    );
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
    rerender(
      <BookingCTA
        pricing={{ ...price, base_price: { ...price.base_price, amount: 0 } }}
        availability={{ next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false }}
        groupSize={groupSize}
        locale="en"
        slug="t"
        tourId={1}
        todayUtc={TODAY_UTC}
      />,
    );
    expect(screen.queryByRole('link', { name: 'Book Now' })).not.toBeInTheDocument();
  });

  it('routes a signed-out traveler to safe login with the full query', () => {
    mockUseAuth.mockReturnValue({ user: null, token: null, isLoading: false });
    renderCta({
      next_available_date: '2026-10-15',
      available_dates: ['2026-10-15'],
      is_unavailable: false,
    });

    const login = screen.getAllByRole('link', { name: 'Book Now' })[0];
    expect(login).toHaveAttribute(
      'href',
      `/en/auth/login?returnUrl=${encodeURIComponent('/en/booking?tour=t&participants=2&date=2026-10-15')}`,
    );
  });
});
