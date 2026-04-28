'use client';

import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useRouter } from 'next/navigation';
import { useLocale } from 'next-intl';
import { User, authApi, AuthApiError } from '../api/auth';
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

  useEffect(() => {
    const restoreSession = async () => {
      try {
        const res = await fetch('/api/auth/session');
        if (res.ok) {
          const data = await res.json();
          if (data && data.user && data.token) {
            setUser(data.user);
            setToken(data.token);
          }
        } else if (res.status === 401) {
          setUser(null);
          setToken(null);
          if (typeof window !== 'undefined') {
            sessionStorage.setItem('sessionExpired', '1');
          }
        }
      } catch (error) {
        console.error('Session restore failed', error);
      } finally {
        setIsLoading(false);
      }
    };
    restoreSession();
  }, []);

  const setAuth = (newUser: User, newToken: string) => {
    setUser(newUser);
    setToken(newToken);
  };

  const login = async (credentials: z.infer<typeof loginSchema>) => {
    setIsLoading(true);
    try {
      const response = await authApi.login(credentials);
      setAuth(response.data, response.token);
      // Here you would also set the httpOnly cookie via a Next.js server route
    } finally {
      setIsLoading(false);
    }
  };

  const register = async (data: z.infer<typeof registerSchema>) => {
    setIsLoading(true);
    try {
      const response = await authApi.register(data);
      setAuth(response.data, response.token);
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async () => {
    if (token) {
      setIsLoading(true);
      try {
        await authApi.logout(token);
      } catch (e) {
        console.error('Logout failed', e);
      } finally {
        setUser(null);
        setToken(null);
        setIsLoading(false);
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
