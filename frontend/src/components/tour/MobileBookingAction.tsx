'use client';

import type { AvailabilityInfo, PricingInfo } from '@/lib/api/types';
import TourBookingController from './TourBookingController';

interface MobileBookingActionProps {
  availability: AvailabilityInfo;
  pricing: PricingInfo;
  groupSize: { min: number; max: number };
  slug: string;
  locale: string;
  tourId?: string | number;
  todayUtc?: string;
}

/** Compatibility wrapper: the canonical TourBookingController owns all
 *  date/count state. This wrapper carries no selection engine of its own.
 *  Action/price labels come from the localized catalogs inside the bar. */
export default function MobileBookingAction({
  availability,
  pricing,
  groupSize,
  slug,
  locale,
  tourId,
  todayUtc,
}: MobileBookingActionProps) {
  return (
    <TourBookingController
      slug={slug}
      locale={locale}
      pricing={pricing}
      availability={availability}
      groupMin={groupSize.min}
      groupMax={groupSize.max}
      todayUtc={todayUtc ?? new Date().toISOString().slice(0, 10)}
      tourId={tourId ?? slug}
    />
  );
}
