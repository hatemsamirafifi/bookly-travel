import { apiClient, GoneError, NotFoundError } from '../client';
import { getTourDetail } from '../tours';

jest.mock('react', () => ({
  ...jest.requireActual('react'),
  cache: (fn: unknown) => {
    const record = globalThis as { __toursCacheCalls?: unknown[] };
    record.__toursCacheCalls = [...(record.__toursCacheCalls ?? []), fn];
    return fn;
  },
}));

// Only the fetch wrapper is replaced; the real error classes must stay intact
// so propagation tests exercise the actual GoneError/NotFoundError identities.
jest.mock('../client', () => ({
  ...jest.requireActual('../client'),
  apiClient: jest.fn(),
}));

const mockedApi = apiClient as unknown as jest.Mock;

describe('getTourDetail', () => {
  beforeEach(() => {
    mockedApi.mockReset();
  });

  it('is keyed through React cache so metadata and page share one request-scoped call', () => {
    const recorded = (globalThis as { __toursCacheCalls?: unknown[] }).__toursCacheCalls;
    expect(recorded).toHaveLength(1);
    expect(recorded?.[0]).toBeInstanceOf(Function);
  });

  it('forwards the exact public tour URL, locale header option and no-store caching', async () => {
    mockedApi.mockResolvedValue({ data: { id: 7 } });

    await getTourDetail('round trip to/to bali', 'es');

    expect(mockedApi).toHaveBeenCalledTimes(1);
    expect(mockedApi).toHaveBeenCalledWith(
      '/api/public/tours/round%20trip%20to%2Fto%20bali?locale=es',
      { locale: 'es', cache: 'no-store' },
    );
  });

  it('encodes every distinct slug/locale pair independently', async () => {
    mockedApi.mockResolvedValueOnce({ data: {} }).mockResolvedValueOnce({ data: {} });

    await getTourDetail('mountain one', 'en');
    await getTourDetail('mountain two', 'it');

    expect(mockedApi).toHaveBeenNthCalledWith(1, '/api/public/tours/mountain%20one?locale=en', {
      locale: 'en',
      cache: 'no-store',
    });
    expect(mockedApi).toHaveBeenNthCalledWith(2, '/api/public/tours/mountain%20two?locale=it', {
      locale: 'it',
      cache: 'no-store',
    });
  });

  it('passes resolution values through untouched', async () => {
    const payload = { data: { title: 'Deep link tour' } };
    mockedApi.mockResolvedValue(payload);

    await expect(getTourDetail('deep-link', 'en')).resolves.toBe(payload);
  });

  it('propagates GoneError unchanged for archived tours', async () => {
    const gone = new GoneError('Tour archived.');
    mockedApi.mockRejectedValue(gone);

    await expect(getTourDetail('archived-tour', 'en')).rejects.toBe(gone);
  });

  it('propagates NotFoundError unchanged for missing tours', async () => {
    const missing = new NotFoundError('Tour not found.');
    mockedApi.mockRejectedValue(missing);

    await expect(getTourDetail('absent-tour', 'es')).rejects.toBe(missing);
  });
});
