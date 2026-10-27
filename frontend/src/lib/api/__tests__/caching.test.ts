import { apiClient } from '../client';
import { getTourDetail } from '../tours';

describe('API Caching', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.clearAllMocks();
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
