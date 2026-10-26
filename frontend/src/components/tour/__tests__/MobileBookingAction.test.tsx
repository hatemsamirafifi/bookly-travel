import { render, screen } from '@testing-library/react';
import MobileBookingAction from '../MobileBookingAction';
import type { AvailabilityInfo, PricingInfo } from '@/lib/api/types';
import enMessages from '../../../../messages/en.json';

// Spec 020 US2 (T029): MobileBookingAction is a thin compatibility wrapper
// over the canonical TourBookingController. Deep-link and unavailable
// regressions are preserved through the shared selection engine.

jest.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => (key: string, values?: Record<string, string | number>) => {
    const root = enMessages as unknown as Record<string, unknown>;
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

const pricing: PricingInfo = { base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' }, tiered_pricing: null };
const availability: AvailabilityInfo = { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false };
const TODAY_UTC = '2026-10-14';

describe('MobileBookingAction', () => {
  beforeEach(() => {
    mockUseAuth.mockReturnValue({ user: { id: 7 }, token: 'token', isLoading: false });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('links to a real offered date and valid minimum group size with a keyboard-focusable action', () => {
    render(<MobileBookingAction availability={availability} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" todayUtc={TODAY_UTC} />);
    const link = screen.getByRole('link', { name: 'Continue to booking' });
    expect(link).toHaveAttribute('href', '/en/booking?tour=rome-walk&participants=2&date=2026-10-15');
    link.focus();
    expect(link).toHaveFocus();
  });

  it('does not render a booking link for an unavailable or inconsistent date', () => {
    const { rerender } = render(<MobileBookingAction availability={{ ...availability, is_unavailable: true }} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" todayUtc={TODAY_UTC} />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
    rerender(<MobileBookingAction availability={{ ...availability, available_dates: [] }} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" todayUtc={TODAY_UTC} />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
