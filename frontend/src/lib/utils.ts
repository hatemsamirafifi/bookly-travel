import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

const ZERO_CENTS = BigInt(0);
const CENTS_PER_EURO = BigInt(100);

// Exact-integer EUR path: binary floating point cannot render every large
// cent value (MAX_SAFE cents end in .91, not .90), so whole units and the
// two-digit remainder come from BigInt arithmetic. Grouping, currency and
// sign placement stay with Intl; the unit count of safe-integer cents is
// always below 2^53, so the Number conversions at the Intl boundary are
// exactly representable.
function formatExactEuroCents(amountCents: number, locale: string): string {
  const total = BigInt(amountCents);
  const negative = total < ZERO_CENTS;
  const magnitude = negative ? -total : total;
  const units = magnitude / CENTS_PER_EURO;
  const remainder = magnitude % CENTS_PER_EURO;
  const unitsNumber = Number(units);
  const fraction = new Intl.NumberFormat(locale, {
    useGrouping: false,
    minimumIntegerDigits: 2,
    maximumFractionDigits: 0,
  }).format(Number(remainder));

  const euro = new Intl.NumberFormat(locale, { style: 'currency', currency: 'EUR' });
  const parts = euro.formatToParts(negative ? -unitsNumber : unitsNumber);
  const rendered = parts.map((part) => (part.type === 'fraction' ? fraction : part.value));
  return rendered.join('');
}

function exactDecimalFallback(amountCents: number): string {
  if (Number.isSafeInteger(amountCents)) {
    const total = BigInt(amountCents);
    const negative = total < ZERO_CENTS;
    const magnitude = negative ? -total : total;
    const whole = magnitude / CENTS_PER_EURO;
    const rest = magnitude % CENTS_PER_EURO;
    return `${negative ? '-' : ''}${whole.toString()}.${rest.toString().padStart(2, '0')}`;
  }
  const value = (Number.isFinite(amountCents) ? amountCents : 0) / 100;
  return value.toFixed(2);
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
  if (currency === 'EUR' && Number.isSafeInteger(amountCents)) {
    try {
      return formatExactEuroCents(amountCents, locale);
    } catch {
      return `${currency} ${exactDecimalFallback(amountCents)}`;
    }
  }
  const value = (Number.isFinite(amountCents) ? amountCents : 0) / 100;
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(value);
  } catch {
    return `${currency} ${value.toFixed(2)}`;
  }
}
