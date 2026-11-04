import { render, screen, fireEvent, act } from '@testing-library/react';
import WishlistHeartButton from '../WishlistHeartButton';
import { isGuestWishlisted } from '@/lib/wishlist/guestWishlist';

const mockUseAuth = jest.fn();
const mockAddWishlist = jest.fn();
const mockRemoveWishlist = jest.fn();
const mockGetStatus = jest.fn();

jest.mock('next-intl', () => ({
  useTranslations: () => {
    const dict: Record<string, string> = {
      save: 'Save to wishlist',
      remove: 'Remove from wishlist',
      savedWith: '{title} saved to wishlist',
      removedWith: '{title} removed from wishlist',
      saveAria: 'Save {title} to wishlist',
      removeAria: 'Remove {title} from wishlist',
      saved: 'Saved to your wishlist.',
      removed: 'Removed from your wishlist.',
    };
    return (key: string, values?: Record<string, unknown>) => {
      let out = dict[key] ?? key;
      for (const [placeholder, value] of Object.entries(values ?? {})) {
        out = out.replace(
          new RegExp(`\\{${placeholder}\\}`, 'g'),
          String(value),
        );
      }
      return out;
    };
  },
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockUseAuth(),
}));

jest.mock('@/lib/api/traveler', () => ({
  addTravelerWishlistItem: (...args: unknown[]) => mockAddWishlist(...args),
  removeTravelerWishlistItem: (...args: unknown[]) => mockRemoveWishlist(...args),
  getTravelerWishlistStatus: (...args: unknown[]) => mockGetStatus(...args),
}));

async function renderAuthenticated(tourId: number, tourTitle: string) {
  mockUseAuth.mockReturnValue({ user: { id: 1 } });
  mockGetStatus.mockResolvedValue({ data: {} });
  mockAddWishlist.mockResolvedValue(undefined);
  mockRemoveWishlist.mockResolvedValue(undefined);
  render(<WishlistHeartButton tourId={tourId} tourTitle={tourTitle} />);
  // Settle the authenticated status fetch started on mount.
  await act(async () => {
    await Promise.resolve();
    await Promise.resolve();
  });
  return screen.getByTestId('wishlist-button');
}

describe('WishlistHeartButton', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    localStorage.clear();
    mockUseAuth.mockReturnValue({ user: null });
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('labels the button with the localized aria-label', async () => {
    const btn = await renderAuthenticated(42, 'Old City Walk');
    expect(btn).toHaveAttribute('aria-label', 'Save Old City Walk to wishlist');
    expect(btn).toHaveAttribute('aria-pressed', 'false');
  });

  it('coalesces three rapid authenticated toggles into a single API call and ends saved', async () => {
    jest.useFakeTimers();
    const btn = await renderAuthenticated(42, 'Old City Walk');

    fireEvent.click(btn);
    fireEvent.click(btn);
    act(() => { jest.advanceTimersByTime(100); });
    fireEvent.click(btn);

    expect(mockAddWishlist).not.toHaveBeenCalled();
    expect(mockRemoveWishlist).not.toHaveBeenCalled();

    act(() => { jest.advanceTimersByTime(350); });
    await act(async () => {});

    expect(mockAddWishlist).toHaveBeenCalledTimes(1);
    expect(mockAddWishlist).toHaveBeenCalledWith(42);
    expect(mockRemoveWishlist).not.toHaveBeenCalled();
    expect(btn).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByText('Old City Walk saved to wishlist')).toBeInTheDocument();
  });

  it('swaps the aria-label to the localized removal label once saved', async () => {
    jest.useFakeTimers();
    const btn = await renderAuthenticated(42, 'Old City Walk');

    fireEvent.click(btn);
    act(() => { jest.advanceTimersByTime(350); });
    await act(async () => {});

    expect(mockAddWishlist).toHaveBeenCalledTimes(1);
    expect(btn).toHaveAttribute('aria-label', 'Remove Old City Walk from wishlist');

    mockRemoveWishlist.mockResolvedValue(undefined);
    fireEvent.click(btn);
    act(() => { jest.advanceTimersByTime(350); });
    await act(async () => {});

    expect(mockRemoveWishlist).toHaveBeenCalledTimes(1);
    expect(mockRemoveWishlist).toHaveBeenCalledWith(42);
    expect(btn).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByText('Old City Walk removed from wishlist')).toBeInTheDocument();
  });

  it('does not call the API when rapid toggling returns to the baseline', async () => {
    jest.useFakeTimers();
    const btn = await renderAuthenticated(42, 'Old City Walk');

    fireEvent.click(btn);
    fireEvent.click(btn);
    act(() => { jest.advanceTimersByTime(1000); });
    await act(async () => {});

    expect(mockAddWishlist).not.toHaveBeenCalled();
    expect(mockRemoveWishlist).not.toHaveBeenCalled();
    expect(btn).toHaveAttribute('aria-pressed', 'false');
  });

  it('applies the guest localStorage toggle once on the final debounced click', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: null });
    render(<WishlistHeartButton tourId={55} tourTitle="Rome Food Tour" />);
    const btn = screen.getByTestId('wishlist-button');
    expect(btn).toHaveAttribute('aria-pressed', 'false');

    fireEvent.click(btn);
    fireEvent.click(btn);
    fireEvent.click(btn);
    expect(isGuestWishlisted(55)).toBe(false);

    act(() => { jest.advanceTimersByTime(350); });
    await act(async () => {});

    expect(isGuestWishlisted(55)).toBe(true);
    expect(btn).toHaveAttribute('aria-pressed', 'true');
    expect(mockAddWishlist).not.toHaveBeenCalled();
    expect(screen.getByText('Rome Food Tour saved to wishlist')).toBeInTheDocument();
  });

  it('announces only the final state, not one message per rapid click', async () => {
    jest.useFakeTimers();
    const btn = await renderAuthenticated(9, 'Sunset Sail');

    fireEvent.click(btn);
    fireEvent.click(btn);
    fireEvent.click(btn);
    act(() => { jest.advanceTimersByTime(500); });
    await act(async () => {});

    // Exactly one localized save announcement — never an intermediate one.
    expect(screen.getAllByText('Sunset Sail saved to wishlist')).toHaveLength(1);
    expect(screen.queryByText('Sunset Sail removed from wishlist')).not.toBeInTheDocument();
  });
});
