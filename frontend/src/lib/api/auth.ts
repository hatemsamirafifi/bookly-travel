import { z } from 'zod';
import { 
  loginSchema, 
  registerSchema, 
  forgotPasswordSchema, 
  resetPasswordSchema, 
  changePasswordSchema 
} from '../validators/auth';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

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

function mapUserApiToUser(apiUser: any): User {
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

async function fetchApi<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const response = await fetch(`${API_URL}/public/auth${endpoint}`, {
    ...options,
    headers,
  });

  const data: any = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(data?.message || 'Authentication failed');
  }

  return data as T;
}

export const authApi = {
  login: async (credentials: z.infer<typeof loginSchema>): Promise<AuthResponse> => {
    const res = await fetchApi<any>('/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
    return { data: mapUserApiToUser(res.data), token: res.token };
  },

  register: async (data: z.infer<typeof registerSchema>): Promise<AuthResponse> => {
    const res = await fetchApi<any>('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
    // Contract returns { data: { user: {...}, token: "..." } }
    return { data: mapUserApiToUser(res.data.user), token: res.data.token };
  },

  logout: async (token: string): Promise<void> => {
    await fetchApi<void>('/logout', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
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

  changePassword: async (token: string, data: z.infer<typeof changePasswordSchema>): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/change-password', {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token}` },
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
  
  resendVerification: async (token: string): Promise<{ message: string }> => {
    return fetchApi<{ message: string }>('/resend-verification', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
    });
  }
};
