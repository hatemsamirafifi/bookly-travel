import { render, screen } from '@testing-library/react';
import MobileBookingAction from '../MobileBookingAction';
import type { AvailabilityInfo, PricingInfo } from '@/lib/api/types';

const pricing: PricingInfo = { base_price: { amount: 8900, currency: 'EUR', formatted: '€89.00' }, tiered_pricing: null };
const availability: AvailabilityInfo = { next_available_date: '2026-10-15', available_dates: ['2026-10-15'], is_unavailable: false };

describe('MobileBookingAction', () => {
  it('links to a real offered date and valid minimum group size with a keyboard-focusable action', () => {
    render(<MobileBookingAction availability={availability} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" actionLabel="Continue to booking" priceLabel="per person" />);
    const link = screen.getByRole('link', { name: 'Continue to booking' });
    expect(link).toHaveAttribute('href', '/en/booking?tour=rome-walk&participants=2&date=2026-10-15');
    link.focus();
    expect(link).toHaveFocus();
  });

  it('does not render a booking link for an unavailable or inconsistent date', () => {
    const { rerender } = render(<MobileBookingAction availability={{ ...availability, is_unavailable: true }} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" actionLabel="Continue to booking" priceLabel="per person" />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
    rerender(<MobileBookingAction availability={{ ...availability, available_dates: [] }} pricing={pricing} groupSize={{ min: 2, max: 8 }} slug="rome-walk" locale="en" actionLabel="Continue to booking" priceLabel="per person" />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });
});
