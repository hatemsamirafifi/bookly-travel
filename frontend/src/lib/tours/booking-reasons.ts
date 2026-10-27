import type { BookingInvalidReason } from './booking-selection';

/** Narrow reason-to-catalog adapter shared by the desktop card and the mobile
 *  bar/dialog (Spec 020 US2). Every key below already exists in the tour or
 *  common catalogs; nothing here invents interface copy. */

export type BookingReasonScope = 'tour' | 'common';

export interface BookingReasonMessage {
  scope: BookingReasonScope;
  key: string;
  values?: Record<string, number | string>;
}

interface ReasonContext {
  groupMin?: unknown;
  groupMax?: unknown;
  dateChanged?: boolean;
  countChanged?: boolean;
}

function countValues(groupMin: unknown, groupMax: unknown): Record<string, number | string> | undefined {
  if (typeof groupMin === 'number' && typeof groupMax === 'number') {
    return { min: groupMin, max: groupMax };
  }
  return undefined;
}

/** Maps one model reason to an existing catalog message. Changed flags select
 *  the explicit reselection explanations over the generic invalid ones. */
export function resolveBookingReasonMessage(
  reason: BookingInvalidReason,
  context: ReasonContext = {},
): BookingReasonMessage {
  switch (reason) {
    case 'tour-unavailable':
      return { scope: 'tour', key: 'unavailable' };
    case 'no-offered-dates':
      return { scope: 'tour', key: 'unavailableHint' };
    case 'invalid-price':
    case 'unsupported-currency':
    case 'invalid-estimate':
      return { scope: 'tour', key: 'booking.invalidPricing' };
    case 'invalid-bounds':
      return { scope: 'tour', key: 'booking.invalidBounds' };
    case 'missing-date':
      return { scope: 'tour', key: 'booking.invalidDate' };
    case 'invalid-date':
      return {
        scope: 'tour',
        key: context.dateChanged === true ? 'booking.dateChanged' : 'booking.invalidDate',
      };
    case 'missing-count':
      return {
        scope: 'tour',
        key: 'booking.invalidCount',
        values: countValues(context.groupMin, context.groupMax),
      };
    case 'invalid-count':
      return {
        scope: 'tour',
        key: context.countChanged === true ? 'booking.countChanged' : 'booking.invalidCount',
        values: countValues(context.groupMin, context.groupMax),
      };
    case 'invalid-route':
      return { scope: 'common', key: 'error' };
  }
}
