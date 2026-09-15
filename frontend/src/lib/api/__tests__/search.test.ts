import { toMinorUnits } from '../search';

describe('toMinorUnits', () => {
  it('converts whole major units to cents', () => {
    expect(toMinorUnits(50)).toBe(5000);
    expect(toMinorUnits(0)).toBe(0);
  });

  it('rounds fractional amounts to whole cents', () => {
    expect(toMinorUnits(49.99)).toBe(4999);
    expect(toMinorUnits(10.005)).toBe(1001);
  });

  it('omits missing or invalid bounds', () => {
    expect(toMinorUnits(undefined)).toBeUndefined();
    expect(toMinorUnits(NaN)).toBeUndefined();
    expect(toMinorUnits(-5)).toBeUndefined();
  });
});
