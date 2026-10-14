import { safeAuthReturnUrl } from '../auth/return-url';
import type { Locale } from '@/i18n/routing';
import type { AvailabilityInfo, PricingInfo } from '../api/types';
import { formatCurrency } from '../utils';

/** Pure booking-selection model (Spec 020 US2). Pure derivations over the
 *  supplied PricingInfo, AvailabilityInfo and group bounds; every date check
 *  takes an injected UTC day. The cents estimate is display-only and the
 *  checkout query carries only tour, participants and date. */

export const BOOKING_SUPPORTED_CURRENCY = 'EUR';

export type BookingInvalidReason =
  | 'tour-unavailable'
  | 'no-offered-dates'
  | 'invalid-price'
  | 'unsupported-currency'
  | 'invalid-bounds'
  | 'missing-date'
  | 'invalid-date'
  | 'missing-count'
  | 'invalid-count'
  | 'invalid-estimate'
  | 'invalid-route';

export interface BookingSelectionInput {
  slug: string;
  locale: string;
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  groupMin: unknown;
  groupMax: unknown;
  selectedDate: string | null | undefined;
  participantCount: unknown;
  todayUtc: string;
}

export interface BookingSelectionView {
  offeredFutureDates: string[];
  initialDate: string | null;
  initialCount: number | null;
  /** Base x count cents; needs valid pricing/bounds/count only, not a date. */
  estimateCents: number | null;
  estimateDisplay: string | null;
  /** Null whenever any reason exists. */
  checkoutUrl: string | null;
  canContinue: boolean;
  reasons: BookingInvalidReason[];
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

/** Exact real YYYY-MM-DD: shape plus a UTC round trip, rejecting 2026-02-30. */
export function isRealCalendarDateString(value: unknown): value is string {
  if (typeof value !== 'string' || !DATE_PATTERN.test(value)) return false;
  const parsed = new Date(`${value}T00:00:00.000Z`);
  if (Number.isNaN(parsed.getTime())) return false;
  return parsed.toISOString().slice(0, 10) === value;
}

export function isPositiveSafeInteger(value: unknown): value is number {
  return typeof value === 'number' && Number.isSafeInteger(value) && value > 0;
}

export function isSupportedCurrency(currency: unknown): boolean {
  return currency === BOOKING_SUPPORTED_CURRENCY;
}

/** Current supported booking routes; currency/locale expansion requires contract review. */
export type BookingLocale = Locale;

const SUPPORTED_LOCALES = ['en', 'es', 'it'] satisfies readonly BookingLocale[];

export function isSupportedLocale(locale: unknown): locale is BookingLocale {
  return typeof locale === 'string' && SUPPORTED_LOCALES.some((supported) => supported === locale);
}

/** Positive safe integers with min <= max; equal bounds are valid. */
export function areGroupBoundsValid(min: unknown, max: unknown): boolean {
  return isPositiveSafeInteger(min) && isPositiveSafeInteger(max) && min <= max;
}

export function isParticipantCountValid(count: unknown, min: unknown, max: unknown): boolean {
  if (!areGroupBoundsValid(min, max)) return false;
  if (!isPositiveSafeInteger(count)) return false;
  return count >= (min as number) && count <= (max as number);
}

function isStrictlyAfterDay(candidate: string, todayUtc: string): boolean {
  return candidate > todayUtc;
}

/** Offered real dates strictly after the injected UTC today, unsliced. */
export function getOfferedFutureDates(
  availability: AvailabilityInfo,
  todayUtc: string,
): string[] {
  if (!isRealCalendarDateString(todayUtc)) return [];
  const dates = availability?.available_dates;
  if (!Array.isArray(dates)) return [];
  return dates.filter(
    (date): date is string =>
      isRealCalendarDateString(date) && isStrictlyAfterDay(date, todayUtc),
  );
}

export function isOfferedFutureDate(
  candidate: unknown,
  availability: AvailabilityInfo,
  todayUtc: string,
): boolean {
  if (!isRealCalendarDateString(candidate)) return false;
  if (!isRealCalendarDateString(todayUtc)) return false;
  if (!isStrictlyAfterDay(candidate, todayUtc)) return false;
  const dates = availability?.available_dates;
  return Array.isArray(dates) && dates.includes(candidate);
}

/** Fail-closed: only an explicit false flag is bookable. */
export function isBookableAvailability(availability: AvailabilityInfo): boolean {
  return availability?.is_unavailable === false;
}

/** Initial date from an offered future next_available_date on a bookable
 *  tour; missing/unlisted/stale next dates yield null, never a substitute. */
export function resolveInitialDate(
  availability: AvailabilityInfo,
  todayUtc: string,
): string | null {
  if (!isBookableAvailability(availability)) return null;
  const next = availability?.next_available_date;
  if (next === null || next === undefined) return null;
  return isOfferedFutureDate(next, availability, todayUtc) ? next : null;
}

export function resolveInitialCount(min: unknown, max: unknown): number | null {
  return areGroupBoundsValid(min, max) ? (min as number) : null;
}

function getBaseAmount(pricing: PricingInfo): unknown {
  return pricing?.base_price?.amount;
}

export function isPriceAmountValid(pricing: PricingInfo): boolean {
  return isPositiveSafeInteger(getBaseAmount(pricing));
}

export function isPriceCurrencySupported(pricing: PricingInfo): boolean {
  return isSupportedCurrency(pricing?.base_price?.currency);
}

function safeProductOf(pricing: PricingInfo, count: unknown): number | null {
  if (!isPriceAmountValid(pricing)) return null;
  if (!isPositiveSafeInteger(count)) return null;
  const product = (getBaseAmount(pricing) as number) * count;
  return Number.isSafeInteger(product) ? product : null;
}

/** Display-only estimate cents; null unless amount, currency, count and the
 *  product are all safe. Callers format only after validation. */
export function getEstimateCents(pricing: PricingInfo, count: unknown): number | null {
  if (!isPriceCurrencySupported(pricing)) return null;
  return safeProductOf(pricing, count);
}

/** Explicit-UTC display; the original string stays the handoff value. */
export function formatOfferedDateUtc(dateString: unknown, locale = 'en'): string {
  if (!isRealCalendarDateString(dateString)) {
    return typeof dateString === 'string' ? dateString : '';
  }
  return new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(`${dateString}T00:00:00.000Z`));
}

/** Syntactic and supported-route checks only; full eligibility lives in the
 *  view. Slug is a query value, so embedded slashes stay encoded, not banned. */
export function buildCheckoutUrl(
  locale: string,
  slug: string,
  participantCount: unknown,
  selectedDate: unknown,
): string | null {
  if (!isSupportedLocale(locale)) return null;
  if (typeof slug !== 'string' || slug.length === 0) return null;
  if (!isPositiveSafeInteger(participantCount)) return null;
  if (!isRealCalendarDateString(selectedDate)) return null;
  return `/${locale}/booking?tour=${encodeURIComponent(slug)}&participants=${participantCount}&date=${encodeURIComponent(selectedDate)}`;
}

/** Wraps the complete same-locale booking path through safeAuthReturnUrl.
 *  Null for an unsupported locale; unsafe paths fall back per that helper. */
export function buildAuthContinuationUrl(locale: string, bookingPath: string): string | null {
  if (!isSupportedLocale(locale)) return null;
  if (typeof bookingPath !== 'string' || bookingPath.length === 0) return null;
  const safe = safeAuthReturnUrl(locale, bookingPath);
  return `/${locale}/auth/login?returnUrl=${encodeURIComponent(safe)}`;
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === 'string' && value.length > 0;
}

/** Explicit reason codes in stable evaluation order; empty means valid. */
export function getBookingInvalidReasons(input: BookingSelectionInput): BookingInvalidReason[] {
  const reasons: BookingInvalidReason[] = [];
  const { slug, locale, pricing, availability, groupMin, groupMax, selectedDate, participantCount, todayUtc } = input;

  if (!isBookableAvailability(availability)) reasons.push('tour-unavailable');
  if (!isSupportedLocale(locale) || !isNonEmptyString(slug)) reasons.push('invalid-route');

  if (!isPriceAmountValid(pricing)) reasons.push('invalid-price');
  if (!isPriceCurrencySupported(pricing)) reasons.push('unsupported-currency');

  const boundsValid = areGroupBoundsValid(groupMin, groupMax);
  if (!boundsValid) reasons.push('invalid-bounds');

  if (getOfferedFutureDates(availability, todayUtc).length === 0) reasons.push('no-offered-dates');

  if (selectedDate === null || selectedDate === undefined || selectedDate === '') {
    reasons.push('missing-date');
  } else if (!isOfferedFutureDate(selectedDate, availability, todayUtc)) {
    reasons.push('invalid-date');
  }

  if (participantCount === null || participantCount === undefined || participantCount === '') {
    reasons.push('missing-count');
  } else if (!isParticipantCountValid(participantCount, groupMin, groupMax)) {
    reasons.push('invalid-count');
  }

  if (
    isPriceAmountValid(pricing) &&
    isPriceCurrencySupported(pricing) &&
    isPositiveSafeInteger(participantCount) &&
    !Number.isSafeInteger((getBaseAmount(pricing) as number) * participantCount)
  ) {
    reasons.push('invalid-estimate');
  }

  return reasons;
}

function areStaticMoneyGuardsValid(input: BookingSelectionInput): boolean {
  return (
    isPriceAmountValid(input.pricing) &&
    isPriceCurrencySupported(input.pricing) &&
    areGroupBoundsValid(input.groupMin, input.groupMax)
  );
}

/** One typed view for both booking surfaces. The estimate needs valid
 *  pricing/bounds/count only; checkout needs every guard. Initial values
 *  appear only when the static guards hold, and the initial date only on a
 *  bookable tour. */
export function getBookingSelectionView(input: BookingSelectionInput): BookingSelectionView {
  const offeredFutureDates = getOfferedFutureDates(input.availability, input.todayUtc);
  const staticGuardsValid = areStaticMoneyGuardsValid(input);
  const initialDate = staticGuardsValid ? resolveInitialDate(input.availability, input.todayUtc) : null;
  const initialCount = resolveInitialCount(input.groupMin, input.groupMax);
  const reasons = getBookingInvalidReasons(input);

  const estimateCents =
    staticGuardsValid && isParticipantCountValid(input.participantCount, input.groupMin, input.groupMax)
      ? safeProductOf(input.pricing, input.participantCount)
      : null;
  const estimateDisplay =
    estimateCents === null
      ? null
      : formatCurrency(estimateCents, BOOKING_SUPPORTED_CURRENCY, input.locale);

  const checkoutUrl =
    reasons.length === 0
      ? buildCheckoutUrl(input.locale, input.slug, input.participantCount, input.selectedDate)
      : null;
  const finalReasons =
    checkoutUrl === null && reasons.length === 0
      ? [...reasons, 'invalid-route' as BookingInvalidReason]
      : reasons;

  return {
    offeredFutureDates,
    initialDate,
    initialCount,
    estimateCents,
    estimateDisplay,
    checkoutUrl,
    canContinue: checkoutUrl !== null && finalReasons.length === 0,
    reasons: finalReasons,
  };
}
