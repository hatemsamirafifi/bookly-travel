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
  email_verified: boolean;
  created_at: string;
  last_login_at: string | null;
}

export interface AuthResponse {
  data: User;
  token: string;
}

async function fetchApi(endpoint: string, options: RequestInit = {}) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...options.headers,
  };

  const response = await fetch(`${API_URL}/public/auth${endpoint}`, {
    ...options,
    headers,
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new Error(data?.message || 'Authentication failed');
  }

  return data;
}

export const authApi = {
  login: async (credentials: z.infer<typeof loginSchema>): Promise<AuthResponse> => {
    return fetchApi('/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  register: async (data: z.infer<typeof registerSchema>): Promise<AuthResponse> => {
    return fetchApi('/register', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  logout: async (token: string): Promise<void> => {
    await fetchApi('/logout', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
    });
  },

  forgotPassword: async (data: z.infer<typeof forgotPasswordSchema>): Promise<{ message: string }> => {
    return fetchApi('/forgot-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  resetPassword: async (data: z.infer<typeof resetPasswordSchema>): Promise<{ message: string }> => {
    return fetchApi('/reset-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  changePassword: async (token: string, data: z.infer<typeof changePasswordSchema>): Promise<{ message: string }> => {
    return fetchApi('/change-password', {
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
    return fetchApi('/resend-verification', {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
    });
  }
};
