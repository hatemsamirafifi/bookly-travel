import { render, screen, fireEvent } from '@testing-library/react';
import WishlistButton from '../WishlistButton';

jest.mock('next-intl', () => ({
  useTranslations: () => {
    const keys: Record<string, string> = {
      remove: 'Remove from wishlist',
      save: 'Save to wishlist',
      promptTitle: 'Sign in to save',
      promptBody: 'Create an account or sign in to save tours to your wishlist.',
      signIn: 'Sign In',
      notNow: 'Not now',
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
  useAuth: () => ({ user: null }),
}));

describe('WishlistButton', () => {
  beforeEach(() => {
    jest.clearAllMocks();
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
});
