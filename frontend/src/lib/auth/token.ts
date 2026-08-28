let tokenGetter: (() => string | null) | null = null;

export function setTokenGetter(fn: () => string | null) {
  tokenGetter = fn;
}

export function getAuthToken(): string | null {
  // Prefer the in-memory token registered by AuthProvider, but never let a
  // not-yet-hydrated getter (null during the first render cycle) shadow the
  // persisted token: child effects can fire requests before AuthProvider's
  // own effect commits the restored token, and returning the stale null here
  // produced spurious 401s on hard loads of authenticated pages.
  if (tokenGetter) {
    const token = tokenGetter();
    if (token) return token;
  }
  if (typeof window !== 'undefined') {
    return localStorage.getItem('auth_token');
  }
  return null;
}
