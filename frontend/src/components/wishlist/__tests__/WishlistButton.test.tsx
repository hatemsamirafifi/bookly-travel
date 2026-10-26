import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { hydrateRoot, type Root } from 'react-dom/client';
import WishlistButton from '../WishlistButton';

const mockUseAuth = jest.fn();
const mockGetAuthToken = jest.fn();
const mockAddWishlistItem = jest.fn();
const mockRemoveWishlistItem = jest.fn();
const mockGetWishlistStatus = jest.fn();

jest.mock('next-intl', () => ({
  useTranslations: () => {
    const keys: Record<string, string> = {
      remove: 'Remove from wishlist',
      save: 'Save to wishlist',
      promptTitle: 'Sign in to save',
      promptBody: 'Create an account or sign in to save tours to your wishlist.',
      signIn: 'Sign In',
      notNow: 'Not now',
      saved: 'Saved to your wishlist.',
      removed: 'Removed from your wishlist.',
      saveFailed: 'Could not update your wishlist. Try again.',
      statusFailed: 'Could not check whether this tour is saved. Try again.',
      denied: 'You cannot update this wishlist.',
      retry: 'Try again',
    };
    return (key: string) => keys[key] || key;
  },
}));

jest.mock('next/link', () => ({
  __esModule: true,
  default: ({ children, href, ...props }: Record<string, unknown>) => (
    <a href={href as string} {...props}>{children as React.ReactNode}</a>
  ),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockUseAuth(),
}));

jest.mock('@/lib/auth/token', () => ({
  getAuthToken: (...args: unknown[]) => mockGetAuthToken(...args),
}));

jest.mock('@/lib/api/traveler', () => ({
  addTravelerWishlistItem: (...args: unknown[]) => mockAddWishlistItem(...args),
  removeTravelerWishlistItem: (...args: unknown[]) => mockRemoveWishlistItem(...args),
  getTravelerWishlistStatus: (...args: unknown[]) => mockGetWishlistStatus(...args),
}));

async function renderAuthenticatedUnsaved(tourId: number | string = 42) {
  mockUseAuth.mockReturnValue({ user: { id: 7 } });
  mockGetAuthToken.mockReturnValue('token-abc');
  mockGetWishlistStatus.mockResolvedValue({ data: {} });
  const rendered = render(<WishlistButton tourId={tourId} locale="en" />);
  await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());
  return rendered;
}

describe('WishlistButton', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockUseAuth.mockReturnValue({ user: null });
    mockGetAuthToken.mockReturnValue(null);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('renders heart icon in unsaved state', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    const btn = screen.getByRole('button');
    expect(btn).toHaveAttribute('aria-pressed', 'false');
    expect(btn).toHaveAttribute('aria-label', 'Save to wishlist');
  });

  it('renders filled heart in saved state', () => {
    render(<WishlistButton tourId={42} locale="en" initialSaved />);

    const btn = screen.getByRole('button');
    expect(btn).toHaveAttribute('aria-pressed', 'true');
    expect(btn).toHaveAttribute('aria-label', 'Remove from wishlist');
  });

  it('shows auth prompt when unauthenticated user clicks', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    fireEvent.click(screen.getByRole('button'));

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Sign in to save')).toBeInTheDocument();
    expect(screen.getByText('Sign In')).toBeInTheDocument();
    expect(screen.getByText('Not now')).toBeInTheDocument();
  });

  it('closes auth prompt when Not now is clicked', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    fireEvent.click(screen.getByRole('button'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();

    fireEvent.click(screen.getByText('Not now'));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('links to login page from auth prompt', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    fireEvent.click(screen.getByRole('button'));

    const signInLink = screen.getByText('Sign In');
    expect(signInLink).toHaveAttribute('href', '/en/auth/login');
  });

  it.each([{}, { compact: true }])(
    'keeps a 44px minimum target (default and compact): %s',
    (props) => {
      render(<WishlistButton tourId={42} locale="en" {...props} />);

      expect(screen.getByTestId('wishlist-button')).toHaveClass('min-h-11', 'min-w-11');
    },
  );

  it('waits for the saved baseline before allowing a debounced removal', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    let resolveStatus!: (value: { data: Record<string, boolean> }) => void;
    let resolveRemove!: (value: unknown) => void;
    mockGetWishlistStatus.mockReturnValue(new Promise((resolve) => { resolveStatus = resolve; }));
    mockAddWishlistItem.mockResolvedValue(undefined);
    mockRemoveWishlistItem.mockReturnValue(new Promise((resolve) => { resolveRemove = resolve; }));
    render(<WishlistButton tourId={42} locale="en" />);

    const button = screen.getByTestId('wishlist-button');
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-pressed', 'mixed');
    expect(button).toHaveAttribute('aria-busy', 'true');
    fireEvent.click(button);
    await act(async () => { jest.advanceTimersByTime(400); });
    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();

    await act(async () => { resolveStatus({ data: { 42: true } }); });
    expect(button).toBeEnabled();
    expect(button).toHaveAttribute('aria-pressed', 'true');
    fireEvent.click(button);
    act(() => { jest.advanceTimersByTime(399); });
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
    act(() => { jest.advanceTimersByTime(1); });
    expect(mockRemoveWishlistItem).toHaveBeenCalledWith(42);
    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    await act(async () => { resolveRemove(undefined); });
    expect(screen.getByRole('status')).toHaveTextContent('Removed from your wishlist.');
  });

  it('does not reuse an earlier tour baseline when returning before pending reads finish', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    let resolveOther!: (value: { data: Record<string, boolean> }) => void;
    let resolveReturn!: (value: { data: Record<string, boolean> }) => void;
    mockGetWishlistStatus
      .mockResolvedValueOnce({ data: { 42: true } })
      .mockReturnValueOnce(new Promise((resolve) => { resolveOther = resolve; }))
      .mockReturnValueOnce(new Promise((resolve) => { resolveReturn = resolve; }));
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    const button = screen.getByTestId('wishlist-button');
    await waitFor(() => expect(button).toHaveAttribute('aria-pressed', 'true'));

    rerender(<WishlistButton tourId={99} locale="en" />);
    expect(button).toHaveAttribute('aria-pressed', 'mixed');
    expect(button).toBeDisabled();
    rerender(<WishlistButton tourId={42} locale="en" />);
    expect(button).toHaveAttribute('aria-pressed', 'mixed');
    expect(button).toBeDisabled();
    await act(async () => { resolveOther({ data: { 99: true } }); });
    expect(button).toHaveAttribute('aria-pressed', 'mixed');
    await act(async () => { resolveReturn({ data: { 42: true } }); });
    expect(button).toBeEnabled();
    expect(button).toHaveAttribute('aria-pressed', 'true');
    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
  });

  it('hydrates consistently when a stored token becomes available only in the browser', async () => {
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {});
    const container = document.createElement('div');
    document.body.appendChild(container);
    let root: Root | undefined;
    const hydrationErrors: unknown[] = [];
    try {
      mockGetWishlistStatus.mockReturnValue(new Promise(() => {}));
      const element = <WishlistButton tourId={42} locale="en" />;
      container.innerHTML = renderToString(element);
      mockGetAuthToken.mockReturnValue('restored-browser-token');
      await act(async () => {
        root = hydrateRoot(container, element, {
          onRecoverableError: (error) => hydrationErrors.push(error),
        });
      });
      expect(hydrationErrors).toEqual([]);
      expect(consoleError).not.toHaveBeenCalled();
    } finally {
      act(() => root?.unmount());
      container.remove();
      consoleError.mockRestore();
    }
  });

  it('announces saved only after the add request resolves', async () => {
    jest.useFakeTimers();
    let resolveAdd!: (value: unknown) => void;
    mockAddWishlistItem.mockReturnValue(new Promise((resolve) => { resolveAdd = resolve; }));
    await renderAuthenticatedUnsaved();

    const btn = screen.getByTestId('wishlist-button');
    fireEvent.click(btn);
    expect(btn).toHaveAttribute('aria-pressed', 'true');

    act(() => { jest.advanceTimersByTime(400); });
    expect(mockAddWishlistItem).toHaveBeenCalledWith(42);
    expect(screen.queryByRole('status')).not.toBeInTheDocument();

    await act(async () => { resolveAdd(undefined); });
    expect(screen.getByRole('status')).toHaveTextContent('Saved to your wishlist.');
    expect(btn).toHaveAttribute('aria-pressed', 'true');
  });

  it('announces removed only after the remove request resolves', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockResolvedValue({ data: { 42: true } });
    let resolveRemove!: (value: unknown) => void;
    mockRemoveWishlistItem.mockReturnValue(new Promise((resolve) => { resolveRemove = resolve; }));
    render(<WishlistButton tourId={42} locale="en" />);

    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });
    fireEvent.click(screen.getByTestId('wishlist-button'));

    act(() => { jest.advanceTimersByTime(400); });
    expect(mockRemoveWishlistItem).toHaveBeenCalledWith(42);
    expect(screen.queryByRole('status')).not.toBeInTheDocument();

    await act(async () => { resolveRemove(undefined); });
    expect(screen.getByRole('status')).toHaveTextContent('Removed from your wishlist.');
  });

  it('rolls back and reports saveFailed without false success when the toggle fails', async () => {
    jest.useFakeTimers();
    mockAddWishlistItem.mockRejectedValue(new Error('network down'));
    await renderAuthenticatedUnsaved();

    fireEvent.click(screen.getByTestId('wishlist-button'));
    act(() => { jest.advanceTimersByTime(400); });

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Could not update your wishlist. Try again.');
    });
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it.each([401, 403])(
    'reports denied without false success on toggle status %s',
    async (status) => {
      jest.useFakeTimers();
      mockAddWishlistItem.mockRejectedValue(Object.assign(new Error('denied'), { status }));
      await renderAuthenticatedUnsaved();

      fireEvent.click(screen.getByTestId('wishlist-button'));
      act(() => { jest.advanceTimersByTime(400); });

      await waitFor(() => {
        expect(screen.getByRole('alert')).toHaveTextContent('You cannot update this wishlist.');
      });
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
      expect(screen.queryByRole('status')).not.toBeInTheDocument();
    },
  );

  it('does not remove a previously unsaved item on rapid save-then-undo before the request', async () => {
    jest.useFakeTimers();
    mockAddWishlistItem.mockResolvedValue(undefined);
    mockRemoveWishlistItem.mockResolvedValue(undefined);
    await renderAuthenticatedUnsaved();

    const btn = screen.getByTestId('wishlist-button');
    fireEvent.click(btn);
    fireEvent.click(btn);
    expect(btn).toHaveAttribute('aria-pressed', 'false');

    await act(async () => { jest.advanceTimersByTime(1000); });

    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('treats a failed status read as unknown with an explicit retry', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockRejectedValueOnce(new Error('status down'));
    render(<WishlistButton tourId={42} locale="en" />);

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Could not check whether this tour is saved. Try again.',
    );

    mockGetWishlistStatus.mockResolvedValue({ data: { 42: true } });
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('ignores a stale toggle failure after the tour changes', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockImplementation((tourId: number | string) =>
      Promise.resolve({ data: { [String(tourId)]: String(tourId) === '99' } }),
    );
    let rejectAdd!: (reason: Error) => void;
    mockAddWishlistItem.mockReturnValue(
      new Promise((_, reject) => { rejectAdd = reject as (reason: Error) => void; }),
    );
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());

    fireEvent.click(screen.getByTestId('wishlist-button'));
    act(() => { jest.advanceTimersByTime(400); });
    expect(mockAddWishlistItem).toHaveBeenCalledWith(42);

    rerender(<WishlistButton tourId={99} locale="en" />);
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });

    await act(async () => { rejectAdd(new Error('stale tour failure')); });

    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('closes the shared dialog on Escape and returns focus to the trigger', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    const trigger = screen.getByRole('button', { name: 'Save to wishlist' });
    trigger.focus();
    fireEvent.click(trigger);

    const dialog = screen.getByRole('dialog', { name: 'Sign in to save' });
    expect(dialog).toBeInTheDocument();
    expect(screen.getAllByRole('button', { name: 'Not now' })).toHaveLength(2);

    fireEvent.keyDown(dialog, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(trigger).toHaveFocus();
  });

  it('reports unknown pressed semantics instead of claiming unsaved after failed status', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockRejectedValue(new Error('status down'));
    render(<WishlistButton tourId={42} locale="en" />);

    const btn = screen.getByTestId('wishlist-button');
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Could not check whether this tour is saved. Try again.',
    );
    expect(btn).toHaveAttribute('aria-pressed', 'mixed');
    expect(btn).toBeDisabled();
    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
  });

  it('keeps an initially saved tour unknown instead of guessing the opposite', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockRejectedValue(new Error('status down'));
    render(<WishlistButton tourId={42} locale="en" initialSaved />);

    await screen.findByRole('alert');
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'mixed');
    expect(screen.getByTestId('wishlist-button')).toBeDisabled();
  });

  it('stays unknown without fake success when retry fails, then recovers on success', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus
      .mockRejectedValueOnce(new Error('status down'))
      .mockRejectedValueOnce(new Error('status still down'));
    render(<WishlistButton tourId={42} locale="en" />);

    await screen.findByRole('alert');
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'mixed');

    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    await waitFor(() => {
      expect(mockGetWishlistStatus).toHaveBeenCalledTimes(2);
    });
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'mixed');
    expect(screen.getByRole('alert')).toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();

    mockGetWishlistStatus.mockResolvedValue({ data: { 42: true } });
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });
    expect(screen.getByTestId('wishlist-button')).not.toBeDisabled();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('ignores a stale toggle success after the session changes', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 1 } });
    mockGetAuthToken.mockReturnValue('token-a');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    let resolveAdd!: (value: unknown) => void;
    mockAddWishlistItem.mockReturnValue(new Promise((resolve) => { resolveAdd = resolve; }));
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());

    fireEvent.click(screen.getByTestId('wishlist-button'));
    act(() => { jest.advanceTimersByTime(400); });
    expect(mockAddWishlistItem).toHaveBeenCalledWith(42);

    // Session changes to B, whose authoritative state is unsaved: the stale
    // success must not fabricate a saved state for the new session.
    mockUseAuth.mockReturnValue({ user: { id: 2 } });
    mockGetAuthToken.mockReturnValue('token-b');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    rerender(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => {
      expect(mockGetWishlistStatus).toHaveBeenCalledTimes(2);
    });
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    });

    await act(async () => { resolveAdd(undefined); });

    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();

    // B's own toggle still works against B's unsaved baseline.
    mockAddWishlistItem.mockResolvedValue(undefined);
    fireEvent.click(screen.getByTestId('wishlist-button'));
    await act(async () => { jest.advanceTimersByTime(400); });
    expect(mockAddWishlistItem).toHaveBeenCalledTimes(2);
    await waitFor(() => {
      expect(screen.getByRole('status')).toHaveTextContent('Saved to your wishlist.');
    });
  });

  it('ignores a stale toggle failure after the session changes', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 1 } });
    mockGetAuthToken.mockReturnValue('token-a');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    let rejectAdd!: (reason: Error) => void;
    mockAddWishlistItem.mockReturnValue(
      new Promise((_, reject) => { rejectAdd = reject as (reason: Error) => void; }),
    );
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());

    fireEvent.click(screen.getByTestId('wishlist-button'));
    act(() => { jest.advanceTimersByTime(400); });

    mockUseAuth.mockReturnValue({ user: { id: 2 } });
    mockGetAuthToken.mockReturnValue('token-b');
    mockGetWishlistStatus.mockResolvedValue({ data: { 42: true } });
    rerender(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });

    await act(async () => { rejectAdd(new Error('old session failure')); });

    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('ignores a stale toggle after sign-out while a request is in flight', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 1 } });
    mockGetAuthToken.mockReturnValue('token-a');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    let resolveAdd!: (value: unknown) => void;
    mockAddWishlistItem.mockReturnValue(new Promise((resolve) => { resolveAdd = resolve; }));
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());

    fireEvent.click(screen.getByTestId('wishlist-button'));
    act(() => { jest.advanceTimersByTime(400); });

    mockUseAuth.mockReturnValue({ user: null });
    mockGetAuthToken.mockReturnValue(null);
    rerender(<WishlistButton tourId={42} locale="en" />);

    await act(async () => { resolveAdd(undefined); });

    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    fireEvent.click(screen.getByTestId('wishlist-button'));
    expect(screen.getByRole('dialog', { name: 'Sign in to save' })).toBeInTheDocument();
  });

  it('drops a pending debounced toggle when the session changes before dispatch', async () => {
    jest.useFakeTimers();
    mockUseAuth.mockReturnValue({ user: { id: 1 } });
    mockGetAuthToken.mockReturnValue('token-a');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => expect(screen.getByTestId('wishlist-button')).toBeEnabled());

    fireEvent.click(screen.getByTestId('wishlist-button'));

    mockUseAuth.mockReturnValue({ user: { id: 2 } });
    mockGetAuthToken.mockReturnValue('token-b');
    mockGetWishlistStatus.mockResolvedValue({ data: { 42: true } });
    rerender(<WishlistButton tourId={42} locale="en" />);

    await act(async () => { jest.advanceTimersByTime(1000); });

    expect(mockAddWishlistItem).not.toHaveBeenCalled();
    expect(mockRemoveWishlistItem).not.toHaveBeenCalled();
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });
  });

  it('clears a failed status when moving to a new tour', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 7 } });
    mockGetAuthToken.mockReturnValue('token-abc');
    mockGetWishlistStatus.mockImplementation((tourId: number | string) =>
      String(tourId) === '42'
        ? Promise.reject(new Error('old tour status down'))
        : Promise.resolve({ data: { [String(tourId)]: true } }),
    );
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);

    await screen.findByRole('alert');
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'mixed');

    rerender(<WishlistButton tourId={99} locale="en" />);
    await waitFor(() => {
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'true');
    });
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('clears a failed status when the session changes', async () => {
    mockUseAuth.mockReturnValue({ user: { id: 1 } });
    mockGetAuthToken.mockReturnValue('token-a');
    mockGetWishlistStatus.mockRejectedValueOnce(new Error('old session status down'));
    const { rerender } = render(<WishlistButton tourId={42} locale="en" />);

    await screen.findByRole('alert');

    mockUseAuth.mockReturnValue({ user: { id: 2 } });
    mockGetAuthToken.mockReturnValue('token-b');
    mockGetWishlistStatus.mockResolvedValue({ data: {} });
    rerender(<WishlistButton tourId={42} locale="en" />);
    await waitFor(() => {
      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
      expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
      expect(screen.getByTestId('wishlist-button')).toBeEnabled();
    });
    expect(screen.getByTestId('wishlist-button')).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByTestId('wishlist-button')).not.toBeDisabled();
  });

  it('exposes the localized prompt description on the shared dialog', () => {
    render(<WishlistButton tourId={42} locale="en" />);

    fireEvent.click(screen.getByRole('button', { name: 'Save to wishlist' }));

    const dialog = screen.getByRole('dialog', { name: 'Sign in to save' });
    const description = screen.getByText('Create an account or sign in to save tours to your wishlist.');
    expect(description).toHaveAttribute('id');
    expect(dialog).toHaveAttribute('aria-describedby', description.getAttribute('id'));
    expect(dialog).toHaveAccessibleDescription(
      'Create an account or sign in to save tours to your wishlist.',
    );
  });
});
