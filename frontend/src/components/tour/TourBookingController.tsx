'use client';

import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { useAuth } from '@/lib/hooks/useAuth';
import type { PricingInfo, AvailabilityInfo } from '@/lib/api/types';
import {
  BOOKING_SUPPORTED_CURRENCY,
  buildAuthContinuationUrl,
  buildRegisterContinuationUrl,
  getBookingSelectionView,
  isOfferedFutureDate,
  isParticipantCountValid,
  isPriceAmountValid,
  isPriceCurrencySupported,
} from '@/lib/tours/booking-selection';
import { formatCurrency } from '@/lib/utils';
import BookingCard, { type BookingCardAuth } from './BookingCard';
import MobileBookingBar from './MobileBookingBar';

export interface TourBookingControllerProps {
  slug: string;
  locale: string;
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  groupMin: number;
  groupMax: number;
  todayUtc: string;
  tourId: string | number;
  cancellationPolicy?: string | null;
  /** Content language of the supplied cancellation policy (e.g. the tour's
   *  content locale); attached only to the policy text, never the controls. */
  cancellationPolicyLocale?: string;
  getTodayUtc?: () => string;
}

const DAY_MS = 24 * 60 * 60 * 1000;

function liveTodayUtc(): string {
  return new Date().toISOString().slice(0, 10);
}

function msUntilNextUtcMidnight(): number {
  const now = Date.now();
  return Math.ceil((now + 1) / DAY_MS) * DAY_MS - now;
}

/** Single page-local owner of the tour booking selection (Spec 020 US2). The
 *  desktop card and the mobile bar/dialog share this date/count state, so a
 *  CSS viewport resize never remounts or resets the traveler's choice. Fresh
 *  props invalidate stale values with an explanation instead of substituting
 *  another date or minimum; a new tour identity resets to fresh initials. */
export default function TourBookingController({
  slug,
  locale,
  pricing,
  availability,
  groupMin,
  groupMax,
  todayUtc,
  tourId,
  cancellationPolicy,
  cancellationPolicyLocale,
  getTodayUtc,
}: TourBookingControllerProps) {
  const { user, isLoading } = useAuth();
  const dateSelectId = useId();
  const clockRef = useRef(getTodayUtc);
  useEffect(() => {
    clockRef.current = getTodayUtc;
  });

  const readClock = useCallback((): string => {
    const clock = clockRef.current;
    return clock ? clock() : liveTodayUtc();
  }, []);

  const [todayUtcState, setTodayUtcState] = useState(todayUtc);
  const refreshTodayUtc = useCallback(() => {
    setTodayUtcState((previous) => {
      const now = readClock();
      return now === previous ? previous : now;
    });
  }, [readClock]);

  const [selectedDate, setSelectedDate] = useState<string | null>(() =>
    getBookingSelectionView({
      slug,
      locale,
      pricing,
      availability,
      groupMin,
      groupMax,
      selectedDate: null,
      participantCount: null,
      todayUtc,
    }).initialDate,
  );
  const [participantCount, setParticipantCount] = useState<number | null>(() =>
    getBookingSelectionView({
      slug,
      locale,
      pricing,
      availability,
      groupMin,
      groupMax,
      selectedDate: null,
      participantCount: null,
      todayUtc,
    }).initialCount,
  );

  // Load-time offer snapshot: distinguishes "changed since page loaded"
  // (explicit reselection required) from values that were never valid.
  const [loadSnapshot, setLoadSnapshot] = useState(() => ({
    offered: getBookingSelectionView({
      slug,
      locale,
      pricing,
      availability,
      groupMin,
      groupMax,
      selectedDate: null,
      participantCount: null,
      todayUtc,
    }).offeredFutureDates,
    bounds: { min: groupMin, max: groupMax },
    day: todayUtc,
  }));

  // New tour identity resets to fresh initials during render (the endorsed
  // previous-render comparison pattern); other prop refreshes only
  // invalidate through the render-path reasons below, never by substitution.
  const [identity, setIdentity] = useState(slug);
  if (identity !== slug) {
    setIdentity(slug);
    const freshDay = getTodayUtc ? getTodayUtc() : liveTodayUtc();
    const fresh = getBookingSelectionView({
      slug,
      locale,
      pricing,
      availability,
      groupMin,
      groupMax,
      selectedDate: null,
      participantCount: null,
      todayUtc: freshDay,
    });
    setSelectedDate(fresh.initialDate);
    setParticipantCount(fresh.initialCount);
    setTodayUtcState(freshDay);
    setLoadSnapshot({
      offered: fresh.offeredFutureDates,
      bounds: { min: groupMin, max: groupMax },
      day: freshDay,
    });
  }

  // UTC-day rollover: refresh at the next UTC midnight, then daily, with full
  // timer cleanup on unmount. The scheduled mount refresh adopts the current
  // UTC day after hydration (the first render, including hydration, uses the
  // supplied snapshot so server markup matches); a pre-midnight document then
  // surfaces the stale selection with its explanation instead of a live
  // continuation.
  useEffect(() => {
    const mountRefresh = setTimeout(refreshTodayUtc, 0);
    let interval: ReturnType<typeof setInterval> | undefined;
    const timeout = setTimeout(() => {
      refreshTodayUtc();
      interval = setInterval(refreshTodayUtc, DAY_MS);
    }, msUntilNextUtcMidnight());
    return () => {
      clearTimeout(mountRefresh);
      clearTimeout(timeout);
      if (interval !== undefined) clearInterval(interval);
    };
  }, [refreshTodayUtc]);

  // Visibility recovery: a backgrounded tab can span UTC midnight.
  useEffect(() => {
    const onVisibilityChange = () => {
      if (!document.hidden) refreshTodayUtc();
    };
    document.addEventListener('visibilitychange', onVisibilityChange);
    return () => document.removeEventListener('visibilitychange', onVisibilityChange);
  }, [refreshTodayUtc]);

  const changeDate = (date: string | null) => {
    refreshTodayUtc();
    setSelectedDate(date);
  };
  const changeCount = (count: number | null) => {
    refreshTodayUtc();
    setParticipantCount(count);
  };

  const view = getBookingSelectionView({
    slug,
    locale,
    pricing,
    availability,
    groupMin,
    groupMax,
    selectedDate,
    participantCount,
    todayUtc: todayUtcState,
  });

  const loadAvailability = {
    available_dates: loadSnapshot.offered,
    next_available_date: null,
    is_unavailable: false,
  };
  const dateChanged =
    selectedDate !== null &&
    view.reasons.includes('invalid-date') &&
    isOfferedFutureDate(selectedDate, loadAvailability, loadSnapshot.day);
  const countChanged =
    participantCount !== null &&
    view.reasons.includes('invalid-count') &&
    isParticipantCountValid(participantCount, loadSnapshot.bounds.min, loadSnapshot.bounds.max);

  const authStatus = isLoading ? 'pending' : user ? 'signed-in' : 'signed-out';  const auth: BookingCardAuth = {
    status: authStatus,
    loginUrl: view.checkoutUrl ? buildAuthContinuationUrl(locale, view.checkoutUrl) : null,
    registerUrl: view.checkoutUrl ? buildRegisterContinuationUrl(locale, view.checkoutUrl) : null,
  };

  // While auth restores (the provider starts pending on SSR), the date/count
  // controls stay inert on both surfaces so a pre-hydration selection cannot
  // revert; provider readiness re-enables them naturally through this state.
  const controlsDisabled = authStatus === 'pending';

  // Validated exact per-person base derived once from cents through the
  // accepted formatter — never the raw server string (which can disagree at
  // the safe-integer extremes) and never formatted from invalid data.
  const baseDisplay =
    isPriceAmountValid(pricing) && isPriceCurrencySupported(pricing)
      ? formatCurrency(pricing.base_price.amount, BOOKING_SUPPORTED_CURRENCY, locale)
      : null;

  // Continue-click recheck: the click itself refreshes the UTC day against
  // the injected clock. A selection that went stale blocks navigation with
  // an explanation; a valid selection keeps native anchor behavior
  // (including modifier-clicks) untouched.
  const guardContinueClick = (event: React.MouseEvent) => {
    const now = getTodayUtc ? getTodayUtc() : liveTodayUtc();
    const fresh = getBookingSelectionView({
      slug,
      locale,
      pricing,
      availability,
      groupMin,
      groupMax,
      selectedDate,
      participantCount,
      todayUtc: now,
    });
    if (!fresh.canContinue || !fresh.checkoutUrl) {
      event.preventDefault();
      setTodayUtcState((previous) => (previous === now ? previous : now));
    }
  };

  // Mobile visibility: the bookable card stays desktop-only (the mobile bar
  // owns bookable small screens), but an unavailable tour must keep its
  // existing localized copy reachable on mobile since the bar returns null.
  const showCardOnMobile = view.reasons.includes('tour-unavailable');

  return (
    <>
      <div
        // Sticky top consumes the measured nav obstruction plus the shared
        // inline token gap (5rem fallback until the island measures).
        className={`${showCardOnMobile ? 'block' : 'hidden'} lg:block lg:sticky lg:top-[calc(var(--tour-section-nav-offset,5rem)+var(--spacing-inline,0.5rem))]`}
        data-testid="tour-booking-card-slot"
      >
        <BookingCard
          locale={locale}
          tourId={tourId}
          baseDisplay={baseDisplay}
          groupMin={groupMin}
          groupMax={groupMax}
          view={view}
          selectedDate={selectedDate}
          participantCount={participantCount}
          dateChanged={dateChanged}
          countChanged={countChanged}
          dateSelectId={dateSelectId}
          onDateChange={changeDate}
          onCountChange={changeCount}
          onContinueClick={guardContinueClick}
          auth={auth}
          cancellationPolicy={cancellationPolicy}
          cancellationPolicyLocale={cancellationPolicyLocale}
          controlsDisabled={controlsDisabled}
        />
      </div>
      <MobileBookingBar
        locale={locale}
        baseDisplay={baseDisplay}
        groupMin={groupMin}
        groupMax={groupMax}
        view={view}
        selectedDate={selectedDate}
        participantCount={participantCount}
        dateChanged={dateChanged}
        countChanged={countChanged}
        onDateChange={changeDate}
        onCountChange={changeCount}
        onContinueClick={guardContinueClick}
        auth={auth}
        controlsDisabled={controlsDisabled}
      />
    </>
  );
}
