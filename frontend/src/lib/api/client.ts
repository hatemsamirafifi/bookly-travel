// Use internal Docker URL for SSR, public URL for browser
const API_URL =
  typeof window === 'undefined' && process.env.API_INTERNAL_URL
    ? process.env.API_INTERNAL_URL
    : process.env.NEXT_PUBLIC_API_URL;

if (!API_URL) {
  throw new Error('NEXT_PUBLIC_API_URL environment variable is required');
}

interface FetchOptions extends RequestInit {
  locale?: string;
  // Kept for backward compatibility with call sites. The backend authenticates
  // public/partner/admin API routes via Sanctum bearer tokens on the stateless
  // `api` middleware group, which does not enforce CSRF (Sanctum's stateful
  // CSRF check only applies to session-cookie SPA requests, which this app
  // never makes). Fetching a CSRF cookie therefore served no purpose and broke
  // in environments where the cookie endpoint was unreachable, so it is ignored.
  requireCsrf?: boolean;
}

export async function apiClient<T>(endpoint: string, options: FetchOptions = {}): Promise<T> {
  const { locale, requireCsrf, ...fetchOptions } = options;
  void requireCsrf; // stripped intentionally — CSRF is not required for bearer-token API (see note above)

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    ...(fetchOptions.headers as Record<string, string>),
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = `${API_URL}${endpoint}`;

  const response = await fetch(url, {
    ...fetchOptions,
    credentials: 'include',
    headers,
  });

  if (!response.ok) {
    if (response.status === 429) {
      const body = await response.json().catch(() => ({}));
      throw new RateLimitError(
        body.message || 'Rate limit exceeded',
        body.retry_after || 60
      );
    }

    if (response.status === 404) {
      throw new NotFoundError('Resource not found');
    }

    if (response.status === 409) {
      const body = await response.json().catch(() => ({}));
      throw new ConflictError(body.message || 'This request conflicts with the current state.');
    }

    if (response.status === 410) {
      const body = await response.json().catch(() => ({}));
      throw new GoneError(body.message || 'This resource is no longer available.');
    }

    if (response.status === 422) {
      const body = await response.json().catch(() => ({}));
      throw new ValidationError(body.message || 'Validation failed', body.errors || {});
    }

    throw new ApiError(
      `API request failed with status ${response.status}`,
      response.status
    );
  }

  return response.json();
}

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

export class NotFoundError extends ApiError {
  constructor(message: string) {
    super(message, 404);
    this.name = 'NotFoundError';
  }
}

export class ConflictError extends ApiError {
  constructor(message: string) {
    super(message, 409);
    this.name = 'ConflictError';
  }
}

export class ValidationError extends ApiError {
  errors: Record<string, string[]>;
  constructor(message: string, errors: Record<string, string[]> = {}) {
    super(message, 422);
    this.name = 'ValidationError';
    this.errors = errors;
  }
}

export class GoneError extends ApiError {
  constructor(message: string) {
    super(message, 410);
    this.name = 'GoneError';
  }
}

export class RateLimitError extends ApiError {
  retryAfter: number;
  constructor(message: string, retryAfter: number) {
    super(message, 429);
    this.name = 'RateLimitError';
    this.retryAfter = retryAfter;
  }
}
