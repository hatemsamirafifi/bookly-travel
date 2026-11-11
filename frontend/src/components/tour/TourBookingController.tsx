'use client';

import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { useAuth } from '@/lib/hooks/useAuth';
import type { PricingInfo, AvailabilityInfo, TourOption } from '@/lib/api/types';
import {
  buildAuthContinuationUrl,
  buildRegisterContinuationUrl,
  getBookingWithOptionView,
  isOfferedFutureDate,
  isParticipantCountValid,
} from '@/lib/tours/booking-selection';
import type { BookingWithOptionView } from '@/lib/tours/booking-selection';
import { formatOptionPriceDisplay } from './OptionSelector';
import BookingCard, { type BookingCardAuth } from './BookingCard';
import MobileBookingBar from './MobileBookingBar';

export interface TourBookingControllerProps {
  slug: string;
  locale: string;
  /** Tour-level pricing/availability fallback; options (when offered) own
   *  the pricing/availability the moment one is selected. */
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  /** Authored bookable options; absent/empty keeps the legacy tour-level flow. */
  options?: TourOption[];
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
 *  desktop card and the mobile bar/dialog share this option/date/count
 *  state, so a CSS viewport resize never remounts or resets the traveler's
 *  choice. Fresh props invalidate stale values with an explanation instead
 *  of substituting another date or minimum; a new tour identity, or a new
 *  option choice (whose own surface differs), resets the affected input.
 *  An option selection is always explicit: with offered options nothing is
 *  guessed from the tour-level surface. */
export default function TourBookingController({
  slug,
  locale,
  pricing,
  availability,
  options,
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

  const offeredOptions = Array.isArray(options) ? options : [];
  const hasOptions = offeredOptions.length > 0;

  const [selectedOptionId, setSelectedOptionId] = useState<number | null>(null);

  // Fresh, unselected start surface: an option-less tour starts from its
  // own availability; a tour with options starts unpicked (nothing offered
  // until an explicit option choice exists).
  const freshStartView = (day: string) =>
    getBookingWithOptionView({
      slug,
      locale,
      pricing,
      availability,
      options,
      groupMin,
      groupMax,
      selectedDate: null,
      participantCount: null,
      selectedOptionId: null,
      todayUtc: day,
    });

  const [selectedDate, setSelectedDate] = useState<string | null>(() =>
    freshStartView(todayUtc).initialDate,
  );
  const [participantCount, setParticipantCount] = useState<number | null>(() =>
    freshStartView(todayUtc).initialCount,
  );

  // Load-time offer snapshot: distinguishes "changed since page loaded"
  // (explicit reselection required) from values that were never valid. The
  // snapshot is keyed to the offered surface — switching options re-snapshots
  // that option's own availability and bounds for changed-detection. With
  // options and no selection yet there are no usable bounds (NaN sentinel);
  // the unpicked surface exposes no controls anyway.
  const [loadSnapshot, setLoadSnapshot] = useState(() => ({
    surfaceKey: `${slug}:`,
    offered: freshStartView(todayUtc).offeredFutureDates,
    bounds: { min: hasOptions ? Number.NaN : groupMin, max: hasOptions ? Number.NaN : groupMax },
    day: todayUtc,
  }));

  // New tour identity resets to fresh initials during render (the endorsed
  // previous-render comparison pattern); other prop refreshes only
  // invalidate through the render-path reasons below, never by substitution.
  const [identity, setIdentity] = useState(slug);
  if (identity !== slug) {
    setIdentity(slug);
    const freshDay = getTodayUtc ? getTodayUtc() : liveTodayUtc();
    const fresh = freshStartView(freshDay);
    setSelectedOptionId(null);
    setSelectedDate(fresh.initialDate);
    setParticipantCount(fresh.initialCount);
    setTodayUtcState(freshDay);
    setLoadSnapshot({
      surfaceKey: `${slug}:`,
      offered: fresh.offeredFutureDates,
      bounds: { min: hasOptions ? Number.NaN : groupMin, max: hasOptions ? Number.NaN : groupMax },
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
  /** Switching options invalidates the previous option's date/count
   *  coordinates: the freshly selected option's own surface re-derives
   *  them (never guessing from the prior option). */
  const changeOption = (option: TourOption | null) => {
    refreshTodayUtc();
    const dayNow = readClock();
    setSelectedOptionId(option?.id ?? null);
    setLoadSnapshot((snapshot) => {
      if (snapshot.surfaceKey === `${slug}:${option?.id ?? ''}`) {
        return snapshot;
      }
      if (option === null) {
        return {
          surfaceKey: `${slug}:`,
          offered: getBookingWithOptionView({
            slug, locale, pricing, availability, options,
            groupMin, groupMax,
            selectedDate: null, participantCount: null,
            selectedOptionId: null,
            todayUtc: dayNow,
          }).offeredFutureDates,
          bounds: { min: Number.NaN, max: Number.NaN },
          day: dayNow,
        };
      }
      const freshOption = getBookingWithOptionView({
        slug, locale, pricing, availability, options,
        groupMin, groupMax,
        selectedDate: null, participantCount: null,
        selectedOptionId: option.id,
        todayUtc: dayNow,
      });
      setSelectedDate(freshOption.initialDate);
      setParticipantCount(freshOption.initialCount);
      return {
        surfaceKey: `${slug}:${option.id}`,
        offered: freshOption.offeredFutureDates,
        bounds: { min: option.group_size?.min ?? Number.NaN, max: option.group_size?.max ?? Number.NaN },
        day: dayNow,
      };
    });
  };

  const view: BookingWithOptionView = getBookingWithOptionView({
    slug,
    locale,
    pricing,
    availability,
    options,
    groupMin,
    groupMax,
    selectedDate,
    participantCount,
    selectedOptionId,
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
  // "Changed" compares against the bounds snapshotted when the current
  // surface was offered (the tour's bounds for option-less tours, the
  // option's bounds at selection time) — not the live props.
  const countChanged =
    participantCount !== null &&
    view.reasons.includes('invalid-count') &&
    isParticipantCountValid(participantCount, loadSnapshot.bounds.min, loadSnapshot.bounds.max);

  const authStatus = isLoading ? 'pending' : user ? 'signed-in' : 'signed-out';
  const auth: BookingCardAuth = {
    status: authStatus,
    loginUrl: view.checkoutUrl ? buildAuthContinuationUrl(locale, view.checkoutUrl) : null,
    registerUrl: view.checkoutUrl ? buildRegisterContinuationUrl(locale, view.checkoutUrl) : null,
  };

  // While auth restores (the provider starts pending on SSR), the option/
  // date/count controls stay inert on both surfaces so a pre-hydration
  // selection cannot revert; provider readiness re-enables them naturally
  // through this state.
  const controlsDisabled = authStatus === 'pending';

  // Validated exact per-person display derived once from cents through the
  // accepted formatter — the selected option's price when one is chosen,
  // otherwise the tour-level base. Never the raw server string (which can
  // disagree at the safe-integer extremes) and never formatted from
  // invalid data.
  const effectivePricing = view.selectedOption ? view.selectedOption.pricing : pricing;
  const baseDisplay = formatOptionPriceDisplay(effectivePricing, locale);

  // Continue-click recheck: the click itself refreshes the UTC day against
  // the injected clock. A selection that went stale blocks navigation with
  // an explanation; a valid selection keeps native anchor behavior
  // (including modifier-clicks) untouched.
  const guardContinueClick = (event: React.MouseEvent) => {
    const now = getTodayUtc ? getTodayUtc() : liveTodayUtc();
    const fresh = getBookingWithOptionView({
      slug,
      locale,
      pricing,
      availability,
      options,
      groupMin,
      groupMax,
      selectedDate,
      participantCount,
      selectedOptionId,
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
          groupMin={view.selectedOption ? view.selectedOption.group_size.min : groupMin}
          groupMax={view.selectedOption ? view.selectedOption.group_size.max : groupMax}
          view={view}
          selectedOptionId={selectedOptionId}
          onOptionChange={changeOption}
          selectedDate={selectedDate}
          participantCount={participantCount}
          controlsDisabled={controlsDisabled}
          dateChanged={dateChanged}
          countChanged={countChanged}
          dateSelectId={dateSelectId}
          onDateChange={changeDate}
          onCountChange={changeCount}
          onContinueClick={guardContinueClick}
          auth={auth}
          cancellationPolicy={cancellationPolicy}
          cancellationPolicyLocale={cancellationPolicyLocale}
        />
      </div>
      <MobileBookingBar
        locale={locale}
        baseDisplay={baseDisplay}
        groupMin={view.selectedOption ? view.selectedOption.group_size.min : groupMin}
        groupMax={view.selectedOption ? view.selectedOption.group_size.max : groupMax}
        view={view}
        selectedOptionId={selectedOptionId}
        onOptionChange={changeOption}
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
