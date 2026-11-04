import { renderHook, act } from '@testing-library/react';
import { useWishlist } from '../useWishlist';
import * as guestWishlist from '../guestWishlist';

let mockUser: { id: number } | null = null;

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: mockUser }),
}));

const mockAddWishlist = jest.fn();
const mockRemoveWishlist = jest.fn();
const mockGetStatus = jest.fn();

jest.mock('@/lib/api/traveler', () => ({
  addTravelerWishlistItem: (...args: unknown[]) => mockAddWishlist(...args),
  removeTravelerWishlistItem: (...args: unknown[]) => mockRemoveWishlist(...args),
  getTravelerWishlistStatus: (...args: unknown[]) => mockGetStatus(...args),
}));

describe('useWishlist', () => {
  beforeEach(() => {
    localStorage.clear();
    mockUser = null;
    mockAddWishlist.mockClear();
    mockRemoveWishlist.mockClear();
    mockGetStatus.mockClear();
  });

  it('toggles wishlist as a guest in localStorage without calling API', async () => {
    const { result } = renderHook(() => useWishlist(101));

    expect(result.current.isSaved).toBe(false);

    await act(async () => {
      await result.current.toggle();
    });

    expect(result.current.isSaved).toBe(true);
    expect(guestWishlist.isGuestWishlisted(101)).toBe(true);
    expect(mockAddWishlist).not.toHaveBeenCalled();

    // Toggle off
    await act(async () => {
      await result.current.toggle();
    });

    expect(result.current.isSaved).toBe(false);
    expect(guestWishlist.isGuestWishlisted(101)).toBe(false);
  });

  it('syncs with authenticated API when user is logged in', async () => {
    mockUser = { id: 1 };
    mockGetStatus.mockResolvedValueOnce({ data: { '202': false } });
    mockAddWishlist.mockResolvedValueOnce({ success: true });

    const { result } = renderHook(() => useWishlist(202));

    await act(async () => {
      await result.current.toggle();
    });

    expect(result.current.isSaved).toBe(true);
    expect(mockAddWishlist).toHaveBeenCalledWith(202);
  });

  it('rolls back optimistic state if authenticated API call fails', async () => {
    mockUser = { id: 1 };
    mockGetStatus.mockResolvedValueOnce({ data: { '202': false } });
    mockAddWishlist.mockRejectedValueOnce(new Error('Network error'));

    const { result } = renderHook(() => useWishlist(303));

    await act(async () => {
      await result.current.toggle();
    });

    expect(result.current.isSaved).toBe(false);
    expect(result.current.error).toBe('Failed to update wishlist');
  });
});
