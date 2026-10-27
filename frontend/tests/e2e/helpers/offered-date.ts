import { expect, type APIRequestContext } from '@playwright/test';

/**
 * Validated live facts for one public tour, used to build checkout URLs.
 *
 * The selected date always comes from `available_dates` itself — explicitly
 * offered and strictly after the current UTC day on a bookable tour — never
 * blindly trusted from `next_available_date`.
 */

export interface OfferedTourFacts {
  slug: string;
  locale: string;
  title: string;
  /** Explicitly offered date strictly after the current UTC day. */
  date: string;
  groupMin: number;
  groupMax: number;
  /** Per-person base in minor units with its currency. */
  baseAmount: number;
  baseCurrency: string;
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

export function utcToday(): string {
  return new Date().toISOString().slice(0, 10);
}

/** Exact estimate text the checkout shows for base × count. */
export function formatEstimateTotal(
  baseAmountCents: number,
  participantCount: number,
  locale: string,
  currency: string,
): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(
    (baseAmountCents * participantCount) / 100,
  );
}

export async function fetchOfferedTourFacts(
  request: APIRequestContext,
  slug: string,
  locale: string,
): Promise<OfferedTourFacts> {
  const response = await request.get(`/api/public/tours/${slug}?locale=${locale}`);
  expect(response.ok()).toBeTruthy();
  const body = await response.json();
  const availability = body.data.availability;
  expect(availability.is_unavailable).toBe(false);
  const availableDates: unknown = availability.available_dates;
  expect(Array.isArray(availableDates) && availableDates.length > 0).toBeTruthy();
  const today = utcToday();
  const future = (availableDates as string[]).filter(
    (day) => DATE_PATTERN.test(day) && day > today,
  );
  expect(future.length).toBeGreaterThan(0);
  const group = body.data.group_size;
  const base = body.data.pricing.base_price;
  return {
    slug,
    locale,
    title: body.data.title as string,
    date: future[0],
    groupMin: group.min as number,
    groupMax: group.max as number,
    baseAmount: base.amount as number,
    baseCurrency: base.currency as string,
  };
}
