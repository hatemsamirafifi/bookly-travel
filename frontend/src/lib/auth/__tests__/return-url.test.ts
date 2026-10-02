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
});
