import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * Format a money amount (stored in integer cents) as a locale-aware currency
 * string. Shared by every booking UI so prices render consistently and the
 * currency is driven by the data, not a hardcoded symbol/EUR fallback.
 *
 * Falls back to a plain decimal when `Intl.NumberFormat` does not recognize the
 * currency code (e.g. a test/sandbox value) so the UI never renders an empty
 * string.
 */
export function formatCurrency(amountCents: number, currency = 'EUR', locale = 'en'): string {
  const value = (Number.isFinite(amountCents) ? amountCents : 0) / 100;
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(value);
  } catch {
    return `${currency} ${value.toFixed(2)}`;
  }
}