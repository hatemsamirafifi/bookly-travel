import React, { ReactNode } from 'react';
import { act, renderHook, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from '../useAuth';

const mockRouter = { push: jest.fn() };

jest.mock('next/navigation', () => ({ useRouter: () => mockRouter }));
jest.mock('next-intl', () => ({ useLocale: () => 'en' }));

describe('AuthProvider logout', () => {
  const originalFetch = global.fetch;

  beforeEach(() => {
    localStorage.clear();
    global.fetch = jest.fn();
    mockRouter.push.mockClear();
  });

  afterEach(() => {
    global.fetch = originalFetch;
    localStorage.clear();
  });

  it('authenticates token revocation before clearing the local session', async () => {
    const fetchMock = jest.mocked(global.fetch);
    fetchMock.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: {
          token: 'current-session-token',
          user: {
            id: 1,
            name: 'Test Traveler',
            email: 'traveler@example.test',
            role: 'traveler',
            locale: 'en',
            email_verified: true,
            created_at: '2026-10-23T00:00:00Z',
            last_login_at: null,
          },
        },
      }),
    } as unknown as Response);

    const wrapper = ({ children }: { children: ReactNode }) => (
      <AuthProvider>{children}</AuthProvider>
    );
    const { result } = renderHook(() => useAuth(), { wrapper });
    await waitFor(() => expect(result.current.isLoading).toBe(false));
    await act(async () => {
      await result.current.login({ email: 'traveler@example.test', password: 'Password123!' });
    });

    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 204,
      json: async (): Promise<unknown> => { throw new SyntaxError('No response body'); },
    } as unknown as Response);
    await act(async () => {
      await result.current.logout();
    });

    expect(fetchMock).toHaveBeenLastCalledWith(
      expect.stringContaining('/api/public/auth/logout'),
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ Authorization: 'Bearer current-session-token' }),
      }),
    );
    expect(result.current.user).toBeNull();
    expect(localStorage.getItem('auth_token')).toBeNull();
  });
});
