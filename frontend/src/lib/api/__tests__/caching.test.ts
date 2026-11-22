import { apiClient } from '../client';
import { searchTours } from '../search';
import { getHomepageData } from '../homepage';
import { getCategories, getCategoryTours } from '../categories';
import { getDestinations, getDestinationTours } from '../destinations';


describe('API Caching', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  describe.each(['en', 'es', 'it'])('fresh public eligibility in %s', (locale) => {
    it.each([
      ['search', () => searchTours({ locale })],
      ['homepage', () => getHomepageData(locale)],
      ['categories', () => getCategories(locale)],
      ['category tours', () => getCategoryTours('walking', { locale })],
      ['destinations', () => getDestinations(locale)],
      ['destination tours', () => getDestinationTours('rome', { locale })],
    ] as const)('keeps %s reads out of the Next data cache', async (_name, read) => {
      (global.fetch as jest.Mock).mockResolvedValue({
        ok: true,
        json: async () => ({ data: [] }),
      });

      await read();

      const options = (global.fetch as jest.Mock).mock.calls[0][1];
      expect(options.cache).toBe('no-store');
      expect(options.next).toBeUndefined();
      expect(options.headers['Accept-Language']).toBe(locale);
    });
  });

  it('should handle rate limit errors gracefully', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: false,
      status: 429,
      json: async () => ({ message: 'Rate limit exceeded', retry_after: 60 }),
    });

    await expect(
      apiClient('/api/test')
    ).rejects.toThrow('Rate limit exceeded');
  });

  it('should handle not found errors', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: false,
      status: 404,
      json: async () => ({}),
    });

    await expect(
      apiClient('/api/nonexistent')
    ).rejects.toThrow('Resource not found');
  });

  it('should set Accept-Language header for localization', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: async () => ({ data: 'test' }),
    });

    await apiClient('/api/test', { locale: 'es' });

    expect(global.fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        headers: expect.objectContaining({
          'Accept-Language': 'es',
        }),
      })
    );
  });

  it('should include credentials for authenticated requests', async () => {
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: async () => ({ data: 'test' }),
    });

    await apiClient('/api/protected');

    expect(global.fetch).toHaveBeenCalledWith(
      expect.any(String),
      expect.objectContaining({
        credentials: 'include',
      })
    );
  });
});
