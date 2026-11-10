import { safeAuthReturnUrl } from '../auth/return-url';
import type { Locale } from '@/i18n/routing';
import type { AvailabilityInfo, PricingInfo, TourOption } from '../api/types';
import { formatCurrency } from '../utils';

/** Pure booking-selection model (Spec 020 US2). Pure derivations over the
 *  supplied PricingInfo, AvailabilityInfo and group bounds; every date check
 *  takes an injected UTC day. The cents estimate is display-only and the
 *  checkout query carries only tour, participants, date and option. */

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
  | 'invalid-route'
  | 'option-required'
  | 'option-invalid';

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
 *  view. Slug is a query value, so embedded slashes stay encoded, not banned.
 *  An option id (positive integer or its decimal string) rides along when
 *  supplied so the explicit selection survives the checkout handoff. */
export function buildCheckoutUrl(
  locale: string,
  slug: string,
  participantCount: unknown,
  selectedDate: unknown,
  optionId?: number | string | null,
): string | null {
  if (!isSupportedLocale(locale)) return null;
  if (typeof slug !== 'string' || slug.length === 0) return null;
  if (!isPositiveSafeInteger(participantCount)) return null;
  if (!isRealCalendarDateString(selectedDate)) return null;
  const base = `/${locale}/booking?tour=${encodeURIComponent(slug)}&participants=${participantCount}&date=${encodeURIComponent(selectedDate)}`;
  // Only a concrete safe-integer option id (or its exact decimal string)
  // is carried; anything else stays out of the handoff.
  const optionIdValue =
    typeof optionId === 'number' && Number.isSafeInteger(optionId) && optionId > 0
      ? String(optionId)
      : typeof optionId === 'string' && /^\d+$/.test(optionId)
        ? optionId
        : null;
  return optionIdValue === null ? base : `${base}&option=${encodeURIComponent(optionIdValue)}`;
}

/** One validated continuation builder behind both auth handoffs: supported
 *  locale, non-empty path and safe same-locale return only. */
function buildValidatedContinuation(
  locale: string,
  bookingPath: string,
  kind: 'login' | 'register',
): string | null {
  if (!isSupportedLocale(locale)) return null;
  if (typeof bookingPath !== 'string' || bookingPath.length === 0) return null;
  const safe = safeAuthReturnUrl(locale, bookingPath);
  return `/${locale}/auth/${kind}?returnUrl=${encodeURIComponent(safe)}`;
}

/** Wraps the complete same-locale booking path through safeAuthReturnUrl.
 *  Null for an unsupported locale; unsafe paths fall back per that helper. */
export function buildAuthContinuationUrl(locale: string, bookingPath: string): string | null {
  return buildValidatedContinuation(locale, bookingPath, 'login');
}

/** Registration counterpart of the login continuation: the same validated
 *  booking path is preserved so choosing registration over login keeps the
 *  full tour/date/count/locale selection. */
export function buildRegisterContinuationUrl(locale: string, bookingPath: string): string | null {
  return buildValidatedContinuation(locale, bookingPath, 'register');
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

/* ─── Option-aware booking view (additive; tour-level behavior unchanged) ── */

export type ResolvedOption =
  | /** Options absent or empty: the tour books from its own surface. */ { kind: 'no-options' }
  /** Options exist but none is selected yet: dates/counts remain unknown. */
  | { kind: 'unpicked' }
  /** A concrete current-tour option is selected. */
  | { kind: 'valid'; option: TourOption }
  /** A selected id that is not among the current tour's options. */
  | { kind: 'invalid' };

/** True when some option coordinate travels along but the tour books
 *  option-less ([] / absent): a stray parameter cannot stay selected. */
export function hasStrayOptionParameter(
  options: readonly TourOption[] | null | undefined,
  selectedOptionId: unknown,
): boolean {
  const carried = selectedOptionId !== null && selectedOptionId !== undefined && selectedOptionId !== '';
  return carried && (Array.isArray(options) ? options.length === 0 : true);
}

/** Validate an option selection against the tour's own offered options.
 *  A numeric id (or its exact decimal string, as query parameters arrive)
 *  must match one offered option; anything else stays unselected/invalid. */
export function resolveSelectedOption(
  options: readonly TourOption[] | null | undefined,
  selectedOptionId: unknown,
): ResolvedOption {
  const list = Array.isArray(options) ? options : [];
  if (list.length === 0) {
    return { kind: 'no-options' };
  }
  if (selectedOptionId === null || selectedOptionId === undefined || selectedOptionId === '') {
    return { kind: 'unpicked' };
  }
  const id =
    typeof selectedOptionId === 'number' && isPositiveSafeInteger(selectedOptionId)
      ? selectedOptionId
      : typeof selectedOptionId === 'string' && /^\d+$/.test(selectedOptionId) && isPositiveSafeInteger(Number(selectedOptionId))
        ? Number(selectedOptionId)
        : NaN;
  const option = list.find((entry) => entry?.id === id);
  return option !== undefined && isPositiveSafeInteger(option.id) ? { kind: 'valid', option } : { kind: 'invalid' };
}

export interface BookingWithOptionInput {
  slug: string;
  locale: string;
  /** Tour-level pricing/availability fallback (no-options booking or the
   *  not-yet-selected display). */
  pricing: PricingInfo;
  availability: AvailabilityInfo;
  /** Tour-level group bounds (fallback when no option is selected). */
  groupMin: unknown;
  groupMax: unknown;
  selectedDate: string | null | undefined;
  participantCount: unknown;
  todayUtc: string;
  options: TourOption[] | null | undefined;
  selectedOptionId: unknown;
}

export interface BookingWithOptionView {
  options: TourOption[];
  /** The concrete selected current-tour option, or null. */
  selectedOption: TourOption | null;
  /** Offered dates of the effective surface; empty before an option exists. */
  offeredFutureDates: string[];
  initialDate: string | null;
  initialCount: number | null;
  estimateCents: number | null;
  estimateDisplay: string | null;
  /** Booking path with the explicit option parameter attached. */
  checkoutUrl: string | null;
  canContinue: boolean;
  reasons: BookingInvalidReason[];
}

/**
 * One option-aware continuation behind every booking surface. The core
 * selection view runs over the selected option's own pricing/availability/
 * bounds; the tour-level surface is used as-is when the tour offers no
 * options ([] or absent, exactly the legacy behavior). With options but no
 * selection, `option-required` is the only surface fact exposed — the
 * option's own coordinates define dates/price, so they never appear early.
 */
export function getBookingWithOptionView(input: BookingWithOptionInput): BookingWithOptionView {
  const options = Array.isArray(input.options) ? input.options : [];
  const resolution = resolveSelectedOption(options, input.selectedOptionId);
  const strayParameter = hasStrayOptionParameter(options, input.selectedOptionId);

  const effectivePricing = resolution.kind === 'valid' ? resolution.option.pricing : input.pricing;
  const effectiveAvailability = resolution.kind === 'valid' ? resolution.option.availability : input.availability;
  const effectiveBounds = resolution.kind === 'valid' ? resolution.option.group_size : null;
  const groupMin = effectiveBounds ? effectiveBounds.min : input.groupMin;
  const groupMax = effectiveBounds ? effectiveBounds.max : input.groupMax;

  const base = getBookingSelectionView({
    slug: input.slug,
    locale: input.locale,
    pricing: effectivePricing,
    availability: effectiveAvailability,
    groupMin,
    groupMax,
    selectedDate: input.selectedDate,
    participantCount: input.participantCount,
    todayUtc: input.todayUtc,
  });

  // Option-less tours keep the tour view verbatim; a carried-but-unusable
  // option coordinate becomes an explicit invalid-selection explanation.
  if (resolution.kind === 'no-options') {
    return {
      options,
      selectedOption: null,
      offeredFutureDates: base.offeredFutureDates,
      initialDate: base.initialDate,
      initialCount: base.initialCount,
      estimateCents: base.estimateCents,
      estimateDisplay: base.estimateDisplay,
      checkoutUrl: base.checkoutUrl,
      canContinue: base.canContinue && !strayParameter,
      reasons: strayParameter ? ['option-invalid', ...base.reasons] : base.reasons,
    };
  }

  if (resolution.kind === 'unpicked' || resolution.kind === 'invalid') {
    // The offered surface belongs to the selected option only; before a
    // concrete option exists, no date/count coordinates can be offered.
    return {
      options,
      selectedOption: null,
      offeredFutureDates: [],
      initialDate: null,
      initialCount: null,
      estimateCents: null,
      estimateDisplay: null,
      checkoutUrl: null,
      canContinue: false,
      reasons: [resolution.kind === 'unpicked' ? 'option-required' : 'option-invalid'],
    };
  }

  const option = resolution.option;
  // The selection coordinate itself rides through checkout and both auth
  // continuation builders (they preserve the full booking path query).
  // base.checkoutUrl is already null whenever any base reason exists.
  const checkoutUrl =
    base.checkoutUrl !== null
      ? buildCheckoutUrl(input.locale, input.slug, input.participantCount, input.selectedDate, option.id)
      : null;

  return {
    options,
    selectedOption: option,
    offeredFutureDates: base.offeredFutureDates,
    initialDate: base.initialDate,
    initialCount: base.initialCount,
    estimateCents: base.estimateCents,
    estimateDisplay: base.estimateDisplay,
    checkoutUrl,
    canContinue: checkoutUrl !== null,
    reasons: base.reasons,
  };
}
