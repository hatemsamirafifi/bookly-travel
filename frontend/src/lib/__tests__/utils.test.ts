import { formatCurrency } from '../utils';

// Shared-formatter precision tests (Spec 020 review delta). Binary floating
// point cannot render every large cent value: MAX_SAFE cents are .91, while
// amountCents/100 formats as .90. EUR safe-integer cents must be exact;
// every other input keeps legacy behavior. Real function, no mocks.
// Non-breaking space built explicitly, never a literal invisible character.
const NBSP = String.fromCharCode(0xa0);

describe('formatCurrency exact EUR integer cents', () => {
  it.each([
    ['en', '€90,071,992,547,409.91'],
    ['es', `90.071.992.547.409,91${NBSP}€`],
    ['it', `90.071.992.547.409,91${NBSP}€`],
  ])('renders exact max-safe cents .91 for locale %s', (locale, expected) => {
    expect(formatCurrency(Number.MAX_SAFE_INTEGER, 'EUR', locale)).toBe(expected);
  });

  it.each([
    ['en', '-€90,071,992,547,409.91'],
    ['es', `-90.071.992.547.409,91${NBSP}€`],
  ])('renders exact negative max-safe cents for locale %s', (locale, expected) => {
    expect(formatCurrency(-Number.MAX_SAFE_INTEGER, 'EUR', locale)).toBe(expected);
  });

  it('keeps normal positive, zero and negative EUR values exact', () => {
    expect(formatCurrency(1099, 'EUR', 'en')).toBe('€10.99');
    expect(formatCurrency(0, 'EUR', 'en')).toBe('€0.00');
    expect(formatCurrency(-1099, 'EUR', 'en')).toBe('-€10.99');
    expect(formatCurrency(-1, 'EUR', 'en')).toBe('-€0.01');
    expect(formatCurrency(-1, 'EUR', 'es')).toBe(`-0,01${NBSP}€`);
    expect(formatCurrency(5, 'EUR', 'en')).toBe('€0.05');
  });

  it('keeps locale decimal, grouping and currency placement', () => {
    expect(formatCurrency(123456789, 'EUR', 'en')).toBe('€1,234,567.89');
    expect(formatCurrency(123456789, 'EUR', 'es')).toBe(`1.234.567,89${NBSP}€`);
    expect(formatCurrency(123456789, 'EUR', 'it')).toBe(`1.234.567,89${NBSP}€`);
  });
});

describe('formatCurrency legacy behavior is preserved', () => {
  it('keeps non-EUR currency formatting untouched', () => {
    expect(formatCurrency(1099, 'USD', 'en')).toBe('$10.99');
    expect(formatCurrency(1099, 'JPY', 'en')).toBe('¥11');
    expect(formatCurrency(1099, 'JPY', 'es')).toBe(`11${NBSP}JPY`);
  });

  it('keeps non-integer and non-finite EUR inputs on the legacy path', () => {
    expect(formatCurrency(10.5, 'EUR', 'en')).toBe(
      new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(10.5 / 100),
    );
    expect(formatCurrency(Number.NaN, 'EUR', 'en')).toBe(
      new Intl.NumberFormat('en', { style: 'currency', currency: 'EUR' }).format(0),
    );
  });

  it('falls back to exact decimal cents text for an unrecognized locale', () => {
    expect(formatCurrency(1099, 'EUR', '!!')).toBe('EUR 10.99');
    expect(formatCurrency(Number.MAX_SAFE_INTEGER, 'EUR', '!!')).toBe('EUR 90071992547409.91');
    expect(formatCurrency(Number.NaN, 'EUR', '!!')).toBe('EUR 0.00');
    expect(formatCurrency(10.55, 'EUR', '!!')).toBe('EUR 0.11');
  });

  it('falls back with the currency code for an unrecognized currency', () => {
    expect(formatCurrency(100, 'XX', 'en')).toBe('XX 1.00');
    expect(formatCurrency(Number.MAX_SAFE_INTEGER - 9, 'XX', 'en')).toBe('XX 90071992547409.81');
  });
});
