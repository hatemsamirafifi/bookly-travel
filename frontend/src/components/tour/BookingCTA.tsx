'use client';

import type { PricingInfo, AvailabilityInfo } from '@/lib/api/types';
import TourBookingController from './TourBookingController';

interface BookingCTAProps {
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  groupSize: { min: number; max: number };
  locale: string;
  slug: string;
  tourId: string | number;
  cancellationPolicy?: string | null;
  todayUtc?: string;
}

/** Compatibility wrapper: the canonical TourBookingController owns all
 *  date/count state. This wrapper carries no selection engine of its own. */
export default function BookingCTA({
  pricing,
  availability,
  groupSize,
  locale,
  slug,
  tourId,
  cancellationPolicy,
  todayUtc,
}: BookingCTAProps) {
  return (
    <TourBookingController
      slug={slug}
      locale={locale}
      pricing={pricing}
      availability={availability}
      groupMin={groupSize.min}
      groupMax={groupSize.max}
      todayUtc={todayUtc ?? new Date().toISOString().slice(0, 10)}
      tourId={tourId}
      cancellationPolicy={cancellationPolicy ?? null}
    />
  );
}
