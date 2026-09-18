'use client';

import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale } from 'next-intl';
import { User, authApi, AuthApiError } from '../api/auth';
import { setTokenGetter } from '../auth/token';
import { z } from 'zod';
import { loginSchema, registerSchema } from '../validators/auth';

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  login: (credentials: z.infer<typeof loginSchema>) => Promise<void>;
  register: (data: z.infer<typeof registerSchema>) => Promise<void>;
  logout: () => Promise<void>;
  setAuth: (user: User, token: string) => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const locale = useLocale();
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  // Register token getter so api/traveler.ts and others use a single source of truth
  useEffect(() => {
    setTokenGetter(() => token);
  }, [token]);

  const restoreSession = useCallback(async () => {
    const storedToken = typeof window !== 'undefined' ? localStorage.getItem('auth_token') : null;
    if (!storedToken) {
      setIsLoading(false);
      return;
    }

    try {
      const user = await authApi.me(storedToken);
      setUser(user);
      setToken(storedToken);
    } catch (error: unknown) {
      // If the request was aborted, cancelled by page navigation, or transient network failure, do not wipe auth state
      if (
        (error instanceof AuthApiError && error.code === 'network_error') ||
        (error instanceof Error &&
          (error.name === 'AbortError' ||
            error.message.includes('aborted') ||
            error.message.includes('Failed to fetch') ||
            error.message.includes('NetworkError') ||
            error.message.includes('Load failed')))
      ) {
        return;
      }
      // Token missing, invalid, or expired — clear local auth state.
      setUser(null);
      setToken(null);
      localStorage.removeItem('auth_token');
      // Only redirect to login on protected routes; public pages stay public
      const pathname = window.location.pathname;
      const protectedPrefixes = [`/${locale}/partner`, `/${locale}/my-bookings`, `/${locale}/profile`, `/${locale}/wishlist`, `/${locale}/reviews`];
      const isProtected = protectedPrefixes.some(p => pathname.startsWith(p));
      if (isProtected) {
        const returnUrl = encodeURIComponent(pathname + window.location.search);
        router.push(`/${locale}/auth/login?sessionExpired=1&returnUrl=${returnUrl}`);
      }
    } finally {
      setIsLoading(false);
    }
  }, [locale, router]);

  useEffect(() => {
    void restoreSession();
  }, [restoreSession]);

  // Cross-tab auth state sync
  useEffect(() => {
    const handleStorage = (e: StorageEvent) => {
      if (e.key === 'auth_token') {
        if (e.newValue) {
          // Token set in another tab — re-hydrate session
          void restoreSession();
        } else {
          // Token removed in another tab — log out
          setUser(null);
          setToken(null);
        }
      }
    };
    window.addEventListener('storage', handleStorage);
    return () => window.removeEventListener('storage', handleStorage);
  }, [restoreSession]);

  const setAuth = (newUser: User, newToken: string) => {
    setUser(newUser);
    setToken(newToken);
    localStorage.setItem('auth_token', newToken);
  };

  // NOTE: login/register/logout intentionally do NOT toggle the global
  // `isLoading` flag. That flag represents session RESTORATION (initial page
  // load) and AuthGuard unmounts its children while it is true. Toggling it
  // around a form submission unmounted the submitting form mid-request, so
  // any server-side error state set after the response (invalid credentials,
  // duplicate email, lockout) was discarded with the unmounted instance and
  // users saw no feedback at all. Forms already disable their own submit
  // buttons via react-hook-form's `isSubmitting`.
  const login = async (credentials: z.infer<typeof loginSchema>) => {
    const response = await authApi.login(credentials);
    setAuth(response.data, response.token);
    // Here you would also set the httpOnly cookie via a Next.js server route
  };

  const register = async (data: z.infer<typeof registerSchema>) => {
    const response = await authApi.register(data);
    setAuth(response.data, response.token);
  };

  const logout = async () => {
    if (token) {
      try {
        await authApi.logout();
      } catch (e) {
        console.error('Logout failed', e);
      } finally {
        setUser(null);
        setToken(null);
        localStorage.removeItem('auth_token');
        // Clear httpOnly cookie here
        router.push(`/${locale}/`);
      }
    }
  };

  return (
    <AuthContext.Provider value={{ user, token, isLoading, login, register, logout, setAuth }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}
