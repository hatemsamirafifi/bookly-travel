import { safeAuthReturnUrl } from '../return-url';

describe('safeAuthReturnUrl', () => {
  it('preserves an internal path and query in the active locale', () => {
    expect(safeAuthReturnUrl('en', '/en/my-bookings?page=2')).toBe('/en/my-bookings?page=2');
  });

  it.each(['https://evil.example', '//evil.example', '/es/profile', '/en\\evil.example', 'javascript:alert(1)'])(
    'rejects unsafe or cross-locale redirect %s', (candidate) => {
      expect(safeAuthReturnUrl('en', candidate)).toBe('/en/');
    },
  );

  // Spec 020 US2: the full selected booking query survives the login round
  // trip unchanged so date/count/locale restoration starts from real state.
  it('preserves a complete same-locale booking query with tour, count and date', () => {
    const bookingPath = '/en/booking?tour=hidden-gems-rome-walking-tour&participants=3&date=2026-10-20';
    expect(safeAuthReturnUrl('en', bookingPath)).toBe(bookingPath);
  });

  it('rejects a cross-locale booking path instead of leaking it across locales', () => {
    expect(
      safeAuthReturnUrl('en', '/es/booking?tour=x&participants=2&date=2026-10-20'),
    ).toBe('/en/');
  });

  it('rejects a backslash booking path', () => {
    expect(
      safeAuthReturnUrl('en', '/en\\booking?tour=x&participants=2&date=2026-10-20'),
    ).toBe('/en/');
  });
});
