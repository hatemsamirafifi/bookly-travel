import { apiClient } from '../client';

describe('apiClient empty success responses', () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    global.fetch = originalFetch;
  });

  it('accepts a bodyless 204 without attempting to parse JSON', async () => {
    global.fetch = jest.fn().mockResolvedValue({
      ok: true,
      status: 204,
      json: jest.fn(() => { throw new Error('No JSON body'); }),
    }) as typeof fetch;

    await expect(apiClient<void>('/api/public/traveler/wishlist/42', { method: 'DELETE' })).resolves.toBeUndefined();
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('/api/public/traveler/wishlist/42'),
      expect.objectContaining({ method: 'DELETE' }),
    );
  });
});
