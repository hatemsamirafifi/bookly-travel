import Link from 'next/link';
import type { AvailabilityInfo, PricingInfo } from '@/lib/api/types';

interface MobileBookingActionProps {
  availability: AvailabilityInfo;
  pricing: PricingInfo;
  groupSize: { min: number; max: number };
  slug: string;
  locale: string;
  actionLabel: string;
  priceLabel: string;
}

export default function MobileBookingAction({ availability, pricing, groupSize, slug, locale, actionLabel, priceLabel }: MobileBookingActionProps) {
  const date = availability.next_available_date;
  if (availability.is_unavailable || !date || !availability.available_dates.includes(date) || pricing.base_price.amount <= 0) {
    return null;
  }

  return (
    <div className="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-surface px-4 py-3 shadow-card lg:hidden" style={{ paddingBottom: 'max(0.75rem, env(safe-area-inset-bottom))' }}>
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
        <p className="text-sm text-bookly-navy"><span className="block font-bold">{pricing.base_price.formatted}</span><span className="text-text-muted">{priceLabel}</span></p>
        <Link
          href={`/${locale}/booking?tour=${encodeURIComponent(slug)}&participants=${groupSize.min}&date=${encodeURIComponent(date)}`}
          className="inline-flex min-h-11 items-center justify-center rounded-xl bg-bookly-gold px-5 py-2.5 text-sm font-semibold text-bookly-navy focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
        >
          {actionLabel}
        </Link>
      </div>
    </div>
  );
}
