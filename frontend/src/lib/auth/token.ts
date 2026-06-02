let tokenGetter: (() => string | null) | null = null;

export function setTokenGetter(fn: () => string | null) {
  tokenGetter = fn;
}

export function getAuthToken(): string | null {
  if (tokenGetter) return tokenGetter();
  if (typeof window !== 'undefined') {
    return localStorage.getItem('auth_token');
  }
  return null;
}
