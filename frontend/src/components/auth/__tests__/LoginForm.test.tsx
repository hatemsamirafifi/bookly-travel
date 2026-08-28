import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { LoginForm } from '../LoginForm';

const mockLogin = jest.fn();
const mockPush = jest.fn();

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ login: mockLogin }),
}));

jest.mock('@/lib/api/auth', () => ({
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
      'signin.title': 'Sign In',
      'signin.emailLabel': 'Email',
      'signin.emailPlaceholder': 'you@example.com',
      'signin.passwordLabel': 'Password',
      'signin.forgotPasswordLink': 'Forgot password?',
      'signin.submitButton': 'Sign In',
      'signin.registerPrompt': "Don't have an account?",
      'signin.registerLink': 'Sign up',
      'signin.showPassword': 'Show password',
      'signin.hidePassword': 'Hide password',
      'errors.invalidCredentials': 'Invalid email or password.',
      'errors.accountLocked': 'Too many failed attempts. Try again later.',
      'errors.sessionExpired': 'Your session has expired. Please sign in again.',
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

describe('LoginForm', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders email, password, and submit button', () => {
    render(<LoginForm />);
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
    expect(screen.getByLabelText('Password')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Sign In/i })).toBeInTheDocument();
  });

  it('shows a register link', () => {
    render(<LoginForm />);
    expect(screen.getByText('Sign up')).toBeInTheDocument();
  });

  it('disables submit button while submitting', async () => {
    mockLogin.mockReturnValue(new Promise(() => {}));
    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'test@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password123' } });

    const form = screen.getByRole('form');
    fireEvent.submit(form);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Sign In/i })).toBeDisabled();
    });
  });

  it('displays server error on invalid credentials', async () => {
    const { AuthApiError } = jest.requireMock('@/lib/api/auth');
    mockLogin.mockRejectedValue(new AuthApiError('Invalid', { code: 'invalid_credentials' }));

    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'test@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'wrongpass' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(screen.getByText('Invalid email or password.')).toBeInTheDocument();
    });
  });

  it('displays lockout message when account is locked', async () => {
    const { AuthApiError } = jest.requireMock('@/lib/api/auth');
    mockLogin.mockRejectedValue(new AuthApiError('Locked', { code: 'account_locked' }));

    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'test@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(
      () => {
        expect(screen.getByText('Too many failed attempts. Try again later.')).toBeInTheDocument();
      },
      { timeout: 5000 }
    );
  });

  it('redirects on successful login', async () => {
    mockLogin.mockResolvedValue(undefined);

    render(<LoginForm />);

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'test@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'password123' } });

    fireEvent.submit(screen.getByRole('form'));

    await waitFor(() => {
      expect(mockPush).toHaveBeenCalledWith('/en/');
    });
  });

  it('shows session expired banner when sessionExpired prop is true', () => {
    render(<LoginForm sessionExpired />);
    expect(screen.getByText('Your session has expired. Please sign in again.')).toBeInTheDocument();
  });
});