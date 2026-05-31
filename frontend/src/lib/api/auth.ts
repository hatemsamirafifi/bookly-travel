import { z } from 'zod';
import { 
  loginSchema, 
  registerSchema, 
  forgotPasswordSchema, 
  resetPasswordSchema, 
  changePasswordSchema 
} from '../validators/auth';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
const CSRF_COOKIE_URL = process.env.NEXT_PUBLIC_CSRF_COOKIE_URL || 'http://localhost:8000';

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'traveler' | 'partner' | 'admin';
  locale: 'en' | 'es' | 'it';
  emailVerified: boolean;
  createdAt: string;
  lastLoginAt: string | null;
}

export interface AuthResponse {
  data: User;
  token: string;
}

export interface UserApiType {
  id: number;
  name: string;
  email: string;
  role: 'traveler' | 'partner' | 'admin';
  locale: 'en' | 'es' | 'it';
  email_verified: boolean;
  created_at: string;
  last_login_at: string | null;
}

interface AuthApiResponse {
  data: {
    user: UserApiType;
    token: string;
  };
}

function mapUserApiToUser(apiUser: UserApiType): User {
  return {
    id: apiUser.id,
    name: apiUser.name,
    email: apiUser.email,
    role: apiUser.role,
    locale: apiUser.locale,
    emailVerified: apiUser.email_verified,
    createdAt: apiUser.created_at,
    lastLoginAt: apiUser.last_login_at,
  };
}

export class AuthApiError extends Error {
  errors?: Record<string, string[]>;
  code?: string;

  constructor(message: string, errors?: Record<string, string[]>, code?: string) {
    super(message);
    this.name = 'AuthApiError';
    this.errors = errors;
    this.code = code;
  }
}

async function fetchCsrfCookie(): Promise<void> {
  await fetch(`${CSRF_COOKIE_URL}/sanctum/csrf-cookie`, {
    method: 'GET',
    credentials: 'include',
  });
}

async function fetchApi<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const response = await fetch(`${API_URL}/public/auth${endpoint}`, {
    ...options,
    headers,
    credentials: 'include',
  });

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const payload = data !== null && typeof data === 'object' ? (data as Record<string, unknown>) : null;
    const message = typeof payload?.message === 'string' ? payload.message : 'Authentication failed';
    const errors = payload?.errors as Record<string, string[]> | undefined;
    const code = typeof payload?.code === 'string' ? payload.code : undefined;
    throw new AuthApiError(message, errors, code);
  }

  return data as T;
}

export const authApi = {
  login: async (credentials: z.infer<typeof loginSchema>): Promise<AuthResponse> => {
    await fetchCsrfCookie();
    const res = await fetchApi<AuthApiResponse>('/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
    return { data: mapUserApiToUser(res.data.user), token: res.data.token };
  },

  register: async (data: z.infer<typeof registerSchema>): Promise<AuthResponse> => {
    await fetchCsrfCookie();
    const res = await fetchApi<AuthApiResponse>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    return { data: mapUserApiToUser(res.data.user), token: res.data.token };
  },

  logout: async (): Promise<void> => {
    await fetchApi<void>('/logout', {
      method: 'POST',
    });
  },

  forgotPassword: async (data: z.infer<typeof forgotPasswordSchema>): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/forgot-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  resetPassword: async (data: z.infer<typeof resetPasswordSchema>): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/reset-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  changePassword: async (data: z.infer<typeof changePasswordSchema>): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/change-password', {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  },

  verifyEmail: async (url: string): Promise<{ message: string }> => {
    // Note: The signed URL is passed directly here instead of endpoint prefix
    const response = await fetch(url, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data?.message || 'Verification failed');
    return data;
  },
  
  resendVerification: async (): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/resend-verification', {
      method: 'POST',
    });
  }
};
