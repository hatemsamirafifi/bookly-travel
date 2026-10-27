import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { RegisterForm } from '../RegisterForm';

const mockSetAuth = jest.fn();
const mockPush = jest.fn();
const mockRegister = jest.fn();

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ setAuth: mockSetAuth }),
}));

jest.mock('@/lib/api/auth', () => ({
  authApi: {
    register: (...args: unknown[]) => mockRegister(...args),
  },
  AuthApiError: class AuthApiError extends Error {
    errors?: Record<string, string[]>;
    code?: string;
    constructor(message: string, opts?: { errors?: Record<string, string[]>; code?: string }) {
      super(message);
      this.errors = opts?.errors;
      this.code = opts?.code;
    }
  },
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: mockPush }),
}));

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => {
    const messages: Record<string, string> = {
      'register.title': 'Create Account',
      'register.nameLabel': 'Full Name',
      'register.namePlaceholder': 'Jane Doe',
      'register.emailLabel': 'Email',
      'register.emailPlaceholder': 'you@example.com',
      'register.passwordLabel': 'Password',
      'register.confirmPasswordLabel': 'Confirm Password',
      'register.submitButton': 'Create Account',
      'register.signinPrompt': 'Already have an account?',
      'register.signinLink': 'Sign in',
      'register.successMessage': 'Account created! Redirecting...',
      'errors.emailTaken': 'This email is already registered.',
      'errors.weakPassword': 'Password must include uppercase, lowercase, and a number.',
    };
    return messages[key] || key;
  },
  useLocale: () => 'en',
}));

jest.mock('next/link', () => {
  const MockLink = ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
  MockLink.displayName = 'MockLink';
  return MockLink;
});

describe('RegisterForm', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('renders name, email, password, and confirm password fields', () => {
    render(<RegisterForm />);
    expect(screen.getByLabelText('Full Name')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
  });

  it('shows a sign-in link', () => {
    render(<RegisterForm />);
    expect(screen.getByText('Sign in')).toBeInTheDocument();
  });

  it('disables submit button while submitting', async () => {
    mockRegister.mockReturnValue(new Promise(() => {}));
    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText('Full Name'), { target: { value: 'Jane Doe' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'jane@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'Password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Create Account/i })).toBeDisabled();
    });
  });

  it('displays success message and redirects on successful registration', async () => {
    mockRegister.mockResolvedValue({
      data: { id: 1, name: 'Jane', email: 'jane@example.com' },
      token: 'test-token',
    });

    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText('Full Name'), { target: { value: 'Jane Doe' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'jane@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'Password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(mockSetAuth).toHaveBeenCalled();
    });

    jest.advanceTimersByTime(1500);

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith('/en/');
    });
  });

  it('displays server error on duplicate email', async () => {
    const { AuthApiError } = jest.requireMock('@/lib/api/auth');
    mockRegister.mockRejectedValue(
      new AuthApiError('Email taken', { errors: { email: ['This email is already registered.'] } })
    );

    render(<RegisterForm />);

    fireEvent.change(screen.getByLabelText('Full Name'), { target: { value: 'Jane Doe' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'jane@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'Password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(screen.getByText('This email is already registered.')).toBeInTheDocument();
    });
  });

  // Spec 020 US2/FR-022: the sign-in cross-link preserves the full validated
  // booking query so returning to login keeps tour/date/count.
  it('preserves the full booking returnUrl on the sign-in cross-link', () => {
    const bookingPath = '/en/booking?tour=hidden-gems&participants=3&date=2026-10-20';
    render(<RegisterForm returnUrl={bookingPath} />);
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
      'href',
      `/en/auth/login?returnUrl=${encodeURIComponent(bookingPath)}`,
    );
  });

  it.each([
    'https://evil.example/en/booking?tour=x',
    '/es/booking?tour=x&participants=2&date=2026-10-20',
    '/en\\evil.example',
  ])('drops an unsafe cross-locale sign-in returnUrl %s', (returnUrl) => {
    render(<RegisterForm returnUrl={returnUrl} />);
    expect(screen.getByRole('link', { name: 'Sign in' })).toHaveAttribute(
      'href',
      '/en/auth/login',
    );
  });

  // Spec 020 US2/FR-022: successful registration returns to the full booking
  // query, never a bare locale fallback, when the supplied return is safe.
  it('redirects to the full booking query on successful registration with a safe returnUrl', async () => {
    const bookingPath = '/en/booking?tour=hidden-gems&participants=3&date=2026-10-20';
    mockRegister.mockResolvedValue({
      data: { id: 1, name: 'Jane', email: 'jane@example.com' },
      token: 'test-token',
    });

    render(<RegisterForm returnUrl={bookingPath} />);

    fireEvent.change(screen.getByLabelText('Full Name'), { target: { value: 'Jane Doe' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'jane@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'Password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(mockSetAuth).toHaveBeenCalled();
    });

    jest.advanceTimersByTime(1500);

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith(bookingPath);
    });
  });
});