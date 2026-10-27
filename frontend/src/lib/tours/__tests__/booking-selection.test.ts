import { buildOfferedDates } from '../../../../tests/fixtures/tour-detail';
import { safeAuthReturnUrl } from '../../auth/return-url';
import { formatCurrency } from '../../utils';
import type { AvailabilityInfo, PricingInfo } from '../../api/types';
import {
  areGroupBoundsValid,
  buildAuthContinuationUrl,
  buildCheckoutUrl,
  formatOfferedDateUtc,
  getBookingSelectionView,
  getEstimateCents,
  getOfferedFutureDates,
  isBookableAvailability,
  isOfferedFutureDate,
  isParticipantCountValid,
  isPositiveSafeInteger,
  isRealCalendarDateString,
  isSupportedCurrency,
  isSupportedLocale,
  resolveInitialCount,
  resolveInitialDate,
} from '../booking-selection';

// Behavioral pure-model tests (Spec 020 T020). They quote the model
// constraints "a real offered `YYYY-MM-DD` date strictly after current UTC
// today", "min/max are positive safe integers and min <= max" and
// "the multiplied result a safe integer". Real objects/fixtures only:
// fixture date builder, actual formatCurrency and actual safeAuthReturnUrl.
// No mocks of the helpers under test.

const TODAY_UTC = '2026-10-14';
const NOW = new Date('2026-10-14T00:00:00.000Z');
const SLUG = 'hidden-gems-rome-walking-tour';

function offered(count = 60): string[] {
  return buildOfferedDates(NOW, count);
}

function pricing(amount: unknown = 1099, currency: unknown = 'EUR'): PricingInfo {
  return {
    base_price: { amount: amount as number, currency: currency as string, formatted: 'unused-in-model' },
    tiered_pricing: null,
  };
}

function availability(
  dates: string[],
  next: string | null,
  isUnavailable = false,
): AvailabilityInfo {
  return { available_dates: dates, next_available_date: next, is_unavailable: isUnavailable };
}

function validViewInput(overrides: Record<string, unknown> = {}) {
  const dates = offered();
  return {
    slug: SLUG,
    locale: 'en',
    pricing: pricing(),
    availability: availability(dates, dates[0]),
    groupMin: 1,
    groupMax: 8,
    selectedDate: dates[2],
    participantCount: 3,
    todayUtc: TODAY_UTC,
    ...overrides,
  };
}

describe('booking-selection date reality (UTC round trip)', () => {
  it.each(['not-a-date', '', '2026-02-30', '2026-13-01', '2026-10-15T00:00:00.000Z', '2026/10/15', '15-10-2026'])(
    'rejects malformed or impossible date %s',
    (value) => {
      expect(isRealCalendarDateString(value)).toBe(false);
    },
  );

  it('accepts a real YYYY-MM-DD date', () => {
    expect(isRealCalendarDateString('2026-10-15')).toBe(true);
  });

  it('rejects non-string values', () => {
    expect(isRealCalendarDateString(null)).toBe(false);
    expect(isRealCalendarDateString(undefined)).toBe(false);
    expect(isRealCalendarDateString(20261015)).toBe(false);
  });
});

describe('booking-selection offered future dates', () => {
  it('rejects today even when listed', () => {
    const dates = [TODAY_UTC, ...offered()];
    const info = availability(dates, TODAY_UTC);
    expect(isOfferedFutureDate(TODAY_UTC, info, TODAY_UTC)).toBe(false);
  });

  it('rejects a past listed date', () => {
    const info = availability(['2026-10-01', ...offered()], '2026-10-01');
    expect(isOfferedFutureDate('2026-10-01', info, TODAY_UTC)).toBe(false);
  });

  it('rejects a real future date that is not offered', () => {
    const dates = offered();
    const info = availability(dates, dates[0]);
    expect(isOfferedFutureDate('2026-12-25', info, TODAY_UTC)).toBe(false);
  });

  it('accepts an exact listed future date and preserves the original string', () => {
    const dates = offered();
    const info = availability(dates, dates[0]);
    const candidate = dates[5];
    expect(isOfferedFutureDate(candidate, info, TODAY_UTC)).toBe(true);
    expect(getOfferedFutureDates(info, TODAY_UTC)).toContain(candidate);
  });

  it('keeps every offered future date reachable, including index 31 (no 30-slice)', () => {
    const dates = offered(60);
    const info = availability(dates, dates[0]);
    const projected = getOfferedFutureDates(info, TODAY_UTC);
    expect(projected).toHaveLength(60);
    expect(projected[31]).toBe(dates[31]);
    const view = getBookingSelectionView(validViewInput({ selectedDate: dates[31] }));
    expect(view.reasons).not.toContain('invalid-date');
    expect(view.checkoutUrl).toContain(`date=${encodeURIComponent(dates[31])}`);
  });

  it('invalidates a now-today selection on UTC-day rollover using only the injected clock', () => {
    const dates = offered();
    const info = availability(dates, dates[0]);
    expect(isOfferedFutureDate(dates[0], info, TODAY_UTC)).toBe(true);
    // Midnight rolls over: the same string is today, never silently replaced.
    expect(isOfferedFutureDate(dates[0], info, dates[0])).toBe(false);
    const rolled = getBookingSelectionView(
      validViewInput({ availability: info, selectedDate: dates[0], todayUtc: dates[0] }),
    );
    expect(rolled.reasons).toContain('invalid-date');
    expect(rolled.checkoutUrl).toBeNull();
  });
});

describe('booking-selection server bookability is fail-closed', () => {
  it('treats an explicit false flag as bookable', () => {
    const dates = offered();
    const info = availability(dates, dates[0], false);
    expect(isBookableAvailability(info)).toBe(true);
    expect(resolveInitialDate(info, TODAY_UTC)).toBe(dates[0]);
  });

  it.each([undefined, null, 'false', 0, 1])(
    'blocks a missing or malformed is_unavailable flag %s',
    (flag) => {
      const dates = offered();
      const info = {
        available_dates: dates,
        next_available_date: dates[0],
        is_unavailable: flag,
      } as unknown as AvailabilityInfo;
      expect(isBookableAvailability(info)).toBe(false);
      expect(resolveInitialDate(info, TODAY_UTC)).toBeNull();
      const view = getBookingSelectionView(validViewInput({ availability: info }));
      expect(view.reasons).toContain('tour-unavailable');
      expect(view.checkoutUrl).toBeNull();
      expect(view.canContinue).toBe(false);
    },
  );
});

describe('booking-selection initial date', () => {
  it('initializes from an offered real future next_available_date when bookable', () => {
    const dates = offered();
    expect(resolveInitialDate(availability(dates, dates[0]), TODAY_UTC)).toBe(dates[0]);
  });

  it('returns null when the next date is missing yet allows an explicit offered selection', () => {
    const dates = offered();
    const info = availability(dates, null);
    expect(resolveInitialDate(info, TODAY_UTC)).toBeNull();
    const view = getBookingSelectionView(validViewInput({ availability: info, selectedDate: dates[4] }));
    expect(view.initialDate).toBeNull();
    expect(view.reasons).toEqual([]);
    expect(view.checkoutUrl).toContain(`date=${encodeURIComponent(dates[4])}`);
  });

  it('never infers a replacement for an inconsistent next date', () => {
    const dates = offered();
    const info = availability(dates, '2026-12-25');
    expect(resolveInitialDate(info, TODAY_UTC)).toBeNull();
  });

  it('returns null for a next date that is today or malformed', () => {
    const dates = offered();
    expect(resolveInitialDate(availability([TODAY_UTC, ...dates], TODAY_UTC), TODAY_UTC)).toBeNull();
    expect(resolveInitialDate(availability(dates, '2026-02-30'), TODAY_UTC)).toBeNull();
  });

  it('returns null when the server flags the tour unavailable', () => {
    const dates = offered();
    const info = availability(dates, dates[0], true);
    expect(resolveInitialDate(info, TODAY_UTC)).toBeNull();
    const view = getBookingSelectionView(validViewInput({ availability: info }));
    expect(view.reasons).toContain('tour-unavailable');
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('leaves the view initial date null when static price or bounds guards are invalid', () => {
    const badBounds = getBookingSelectionView(validViewInput({ groupMin: 9, groupMax: 8 }));
    expect(badBounds.reasons).toContain('invalid-bounds');
    expect(badBounds.initialDate).toBeNull();
    expect(badBounds.checkoutUrl).toBeNull();
    const badPrice = getBookingSelectionView(validViewInput({ pricing: pricing(0, 'EUR') }));
    expect(badPrice.reasons).toContain('invalid-price');
    expect(badPrice.initialDate).toBeNull();
    expect(badPrice.checkoutUrl).toBeNull();
  });
});

describe('booking-selection group bounds and counts', () => {
  it('accepts equal bounds as valid', () => {
    expect(areGroupBoundsValid(4, 4)).toBe(true);
    expect(resolveInitialCount(4, 4)).toBe(4);
  });

  it('initializes the count to min only when bounds are valid', () => {
    expect(resolveInitialCount(2, 8)).toBe(2);
    expect(resolveInitialCount(0, 8)).toBeNull();
    expect(resolveInitialCount(9, 8)).toBeNull();
  });

  it.each([0, -1, 2.5, Number.NaN, Number.POSITIVE_INFINITY, Number.MAX_SAFE_INTEGER + 1, '2', null])(
    'rejects invalid bound value %s',
    (value) => {
      expect(isPositiveSafeInteger(value)).toBe(false);
      expect(areGroupBoundsValid(value, 8)).toBe(false);
      expect(areGroupBoundsValid(1, value)).toBe(false);
    },
  );

  it('rejects min greater than max', () => {
    expect(areGroupBoundsValid(8, 2)).toBe(false);
  });

  it('keeps counts inside bounds and rejects fractional/nonfinite/unsafe/outside counts', () => {
    expect(isParticipantCountValid(1, 1, 8)).toBe(true);
    expect(isParticipantCountValid(8, 1, 8)).toBe(true);
    expect(isParticipantCountValid(0, 1, 8)).toBe(false);
    expect(isParticipantCountValid(9, 1, 8)).toBe(false);
    expect(isParticipantCountValid(2.5, 1, 8)).toBe(false);
    expect(isParticipantCountValid(Number.NaN, 1, 8)).toBe(false);
    expect(isParticipantCountValid(Number.POSITIVE_INFINITY, 1, 8)).toBe(false);
    expect(isParticipantCountValid(Number.MAX_SAFE_INTEGER + 1, 1, Number.MAX_SAFE_INTEGER + 1)).toBe(false);
  });

  it('reports invalid bounds with no checkout URL', () => {
    const view = getBookingSelectionView(validViewInput({ groupMin: 9, groupMax: 8 }));
    expect(view.reasons).toContain('invalid-bounds');
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('distinguishes a missing count from an invalid count', () => {
    const missing = getBookingSelectionView(validViewInput({ participantCount: null }));
    expect(missing.reasons).toContain('missing-count');
    expect(missing.checkoutUrl).toBeNull();
    const invalid = getBookingSelectionView(validViewInput({ participantCount: 99 }));
    expect(invalid.reasons).toContain('invalid-count');
    expect(invalid.checkoutUrl).toBeNull();
  });

  it('distinguishes a missing date from a stale/unlisted date', () => {
    const missing = getBookingSelectionView(validViewInput({ selectedDate: null }));
    expect(missing.reasons).toContain('missing-date');
    expect(missing.checkoutUrl).toBeNull();
    const stale = getBookingSelectionView(validViewInput({ selectedDate: '2026-12-25' }));
    expect(stale.reasons).toContain('invalid-date');
    expect(stale.checkoutUrl).toBeNull();
  });
});

describe('booking-selection pricing and estimate', () => {
  it('computes the exact EUR cents estimate 1099x3=3297 and formats with the real formatter', () => {
    expect(getEstimateCents(pricing(1099, 'EUR'), 3)).toBe(3297);
    const view = getBookingSelectionView(validViewInput({}));
    expect(view.estimateCents).toBe(3297);
    expect(view.estimateDisplay).toBe(formatCurrency(3297, 'EUR', 'en'));
  });

  it('computes the estimate for a valid count even when the date is missing', () => {
    const view = getBookingSelectionView(validViewInput({ selectedDate: null }));
    expect(view.reasons).toContain('missing-date');
    expect(view.estimateCents).toBe(3297);
    expect(view.estimateDisplay).toBe(formatCurrency(3297, 'EUR', 'en'));
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('computes the estimate for a valid count even when the date is stale', () => {
    const view = getBookingSelectionView(validViewInput({ selectedDate: '2026-12-25' }));
    expect(view.reasons).toContain('invalid-date');
    expect(view.estimateCents).toBe(3297);
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('computes the estimate for a valid count even when the tour is unavailable', () => {
    const dates = offered();
    const view = getBookingSelectionView(
      validViewInput({ availability: availability(dates, dates[0], true) }),
    );
    expect(view.reasons).toContain('tour-unavailable');
    expect(view.estimateCents).toBe(3297);
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('computes the exact max-safe estimate for base MAX_SAFE x count 1', () => {
    const view = getBookingSelectionView(
      validViewInput({
        pricing: pricing(Number.MAX_SAFE_INTEGER, 'EUR'),
        groupMin: 1,
        groupMax: 1,
        participantCount: 1,
      }),
    );
    expect(view.reasons).toEqual([]);
    expect(view.canContinue).toBe(true);
    expect(view.estimateCents).toBe(Number.MAX_SAFE_INTEGER);
    expect(view.estimateDisplay).toBe(formatCurrency(Number.MAX_SAFE_INTEGER, 'EUR', 'en'));
    expect(view.estimateDisplay).toContain('.91');
  });

  it.each([0, -5, 10.5, Number.NaN, Number.POSITIVE_INFINITY, Number.MAX_SAFE_INTEGER + 1])(
    'rejects invalid base amount %s before formatting',
    (amount) => {
      expect(getEstimateCents(pricing(amount, 'EUR'), 2)).toBeNull();
      const view = getBookingSelectionView(
        validViewInput({ pricing: pricing(amount, 'EUR'), participantCount: 2 }),
      );
      expect(view.reasons).toContain('invalid-price');
      expect(view.estimateCents).toBeNull();
      // The formatter zero fallback must never conceal bad money.
      expect(view.estimateDisplay).toBeNull();
      expect(view.checkoutUrl).toBeNull();
    },
  );

  it.each(['JPY', 'USD'])('rejects unsupported currency %s', (currency) => {
    expect(isSupportedCurrency(currency)).toBe(false);
    const view = getBookingSelectionView(
      validViewInput({ pricing: pricing(1099, currency), participantCount: 2 }),
    );
    expect(view.reasons).toContain('unsupported-currency');
    expect(view.estimateCents).toBeNull();
    expect(view.estimateDisplay).toBeNull();
    expect(view.checkoutUrl).toBeNull();
  });

  it('rejects unsafe multiplication overflow without an enabled checkout', () => {
    expect(getEstimateCents(pricing(Number.MAX_SAFE_INTEGER, 'EUR'), 2)).toBeNull();
    const view = getBookingSelectionView(
      validViewInput({
        pricing: pricing(Number.MAX_SAFE_INTEGER, 'EUR'),
        groupMin: 1,
        groupMax: 2,
        participantCount: 2,
      }),
    );
    expect(view.reasons).toContain('invalid-estimate');
    expect(view.estimateCents).toBeNull();
    expect(view.estimateDisplay).toBeNull();
    expect(view.checkoutUrl).toBeNull();
  });

  it('reports no offered dates when every date is stale', () => {
    const info = availability(['2026-10-01'], '2026-10-01');
    const view = getBookingSelectionView(
      validViewInput({ availability: info, selectedDate: '2026-10-01' }),
    );
    expect(getOfferedFutureDates(info, TODAY_UTC)).toEqual([]);
    expect(view.reasons).toContain('no-offered-dates');
    expect(view.checkoutUrl).toBeNull();
  });
});

describe('booking-selection checkout and auth continuation URLs', () => {
  it('supports exactly the en, es and it locales', () => {
    expect(isSupportedLocale('en')).toBe(true);
    expect(isSupportedLocale('es')).toBe(true);
    expect(isSupportedLocale('it')).toBe(true);
    expect(isSupportedLocale('fr')).toBe(false);
    expect(isSupportedLocale('en-US')).toBe(false);
    expect(isSupportedLocale('')).toBe(false);
    expect(isSupportedLocale(null)).toBe(false);
  });

  it('builds the exact full checkout query with encoded slug and preserved date string', () => {
    const dates = offered();
    const url = buildCheckoutUrl('en', 'rome & hidden/gems', 3, dates[2]);
    expect(url).toBe(
      `/en/booking?tour=${encodeURIComponent('rome & hidden/gems')}&participants=3&date=${encodeURIComponent(dates[2])}`,
    );
  });

  it.each(['fr', 'en-US', 'en/', '', '/en', 'EN'])(
    'refuses a checkout URL for unsupported locale %s',
    (locale) => {
      const dates = offered();
      expect(buildCheckoutUrl(locale, SLUG, 3, dates[2])).toBeNull();
    },
  );

  it.each(['fr', '', 'en/'])(
    'refuses an auth continuation for unsupported locale %s',
    (locale) => {
      const dates = offered();
      expect(buildAuthContinuationUrl(locale, `/en/booking?tour=${SLUG}&participants=3&date=${dates[2]}`)).toBeNull();
    },
  );

  it('explains an unbuildable route with invalid-route instead of a silent block', () => {
    const view = getBookingSelectionView(validViewInput({ locale: 'fr' }));
    expect(view.reasons).toContain('invalid-route');
    expect(view.checkoutUrl).toBeNull();
    expect(view.canContinue).toBe(false);
  });

  it('exposes the validated checkout URL on a fully valid view', () => {
    const dates = offered();
    const view = getBookingSelectionView(validViewInput({}));
    expect(view.reasons).toEqual([]);
    expect(view.canContinue).toBe(true);
    expect(view.checkoutUrl).toBe(
      `/en/booking?tour=${encodeURIComponent(SLUG)}&participants=3&date=${encodeURIComponent(dates[2])}`,
    );
  });

  it('wraps the complete same-locale booking path through the real safe return-url helper', () => {
    const dates = offered();
    const bookingPath = buildCheckoutUrl('en', SLUG, 3, dates[2]) as string;
    const continued = buildAuthContinuationUrl('en', bookingPath) as string;
    expect(continued).toBe(
      `/en/auth/login?returnUrl=${encodeURIComponent(safeAuthReturnUrl('en', bookingPath))}`,
    );
    expect(continued).toContain(encodeURIComponent(bookingPath));
  });

  it.each(['https://evil.example/en/booking?tour=x', '/es/booking?tour=x&participants=2&date=2026-10-20', '/en\\evil'])(
    'rejects external, cross-locale and backslash booking paths %s',
    (badPath) => {
      expect(buildAuthContinuationUrl('en', badPath)).toBe(
        `/en/auth/login?returnUrl=${encodeURIComponent(safeAuthReturnUrl('en', badPath))}`,
      );
      expect(safeAuthReturnUrl('en', badPath)).toBe('/en/');
    },
  );
});

describe('booking-selection UTC display and clock independence', () => {
  it.each([
    ['en', '10/15/2026'],
    ['es', '15/10/2026'],
    ['it', '15/10/2026'],
  ])('formats a known date for locale %s without shifting the calendar day', (locale, expected) => {
    expect(formatOfferedDateUtc('2026-10-15', locale)).toBe(expected);
  });

  it('passes invalid input through instead of inventing a display', () => {
    expect(formatOfferedDateUtc('2026-02-30', 'en')).toBe('2026-02-30');
    expect(formatOfferedDateUtc(null, 'en')).toBe('');
  });

  it('keeps the original date string intact for checkout handoff', () => {
    const dates = offered();
    const view = getBookingSelectionView(validViewInput({ selectedDate: dates[7] }));
    expect(view.checkoutUrl).toContain(`date=${encodeURIComponent(dates[7])}`);
    expect(view.checkoutUrl).not.toContain(new Date(dates[7]).toString());
  });

  it('depends only on the injected UTC day, not the host clock', () => {
    const dates = offered();
    const info = availability(dates, dates[0]);
    const expectedProjection = getOfferedFutureDates(info, TODAY_UTC);
    const expectedView = getBookingSelectionView(validViewInput({}));
    for (const systemTime of ['1999-01-01T00:00:00.000Z', '2035-06-30T12:00:00.000Z']) {
      jest.useFakeTimers();
      jest.setSystemTime(new Date(systemTime));
      try {
        expect(getOfferedFutureDates(info, TODAY_UTC)).toEqual(expectedProjection);
        expect(isOfferedFutureDate(dates[2], info, TODAY_UTC)).toBe(true);
        expect(getBookingSelectionView(validViewInput({}))).toEqual(expectedView);
      } finally {
        jest.useRealTimers();
      }
    }
  });

  it('invalidates the original date when the injected day advances, without substitution', () => {
    const dates = offered();
    const info = availability(dates, dates[0]);
    expect(isOfferedFutureDate(dates[0], info, TODAY_UTC)).toBe(true);
    expect(isOfferedFutureDate(dates[0], info, dates[0])).toBe(false);
    expect(getOfferedFutureDates(info, dates[0])).not.toContain(dates[0]);
  });
});
