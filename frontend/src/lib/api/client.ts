// Use internal Docker URL for SSR, public URL for browser
// Server-side requests (SSR/ISR inside Docker) use the internal service
// hostname; browser requests use NEXT_PUBLIC_API_URL when set (e.g. native
// local dev against http://localhost:8080) or fall back to same-origin
// relative URLs, which nginx proxies to the Laravel API. Never expose a
// Docker-internal hostname to browser JavaScript.
const API_URL =
  typeof window === 'undefined' && process.env.API_INTERNAL_URL
    ? process.env.API_INTERNAL_URL
    : process.env.NEXT_PUBLIC_API_URL || '';

if (typeof window === 'undefined' && !API_URL) {
  throw new Error('API URL configuration is required: set API_INTERNAL_URL or NEXT_PUBLIC_API_URL');
}

/**
 * Resolved API base URL for call sites that build their own fetch requests
 * (e.g. partner pages attaching bearer tokens manually). Returns the same
 * value apiClient uses: internal hostname for SSR, NEXT_PUBLIC_API_URL or
 * same-origin relative ('') in the browser. Never hardcode an origin.
 */
export function getApiBaseUrl(): string {
  return API_URL;
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
  // Opt-in Next.js ISR revalidation (seconds) for read-heavy public reads.
  // NOT a blanket default: `apiClient` is shared with authenticated endpoints
  // (partner/admin/booking), which must never be cached. Pass `revalidate`
  // only from public read functions whose data tolerates short staleness.
  revalidate?: number;
}

/**
 * Build a URLSearchParams from a params object, skipping empty/null/undefined
 * values. Shared by the search/category/destination query builders so the
 * field list can't drift between them (spec 006 reuse cleanup). Pass `omit`
 * for scope routes that must exclude a param the slug supersedes (e.g.
 * `category` on category tours, `location` on destination tours).
 */
export function buildSearchParams(
  params: object,
  omit: string[] = []
): URLSearchParams {
  const sp = new URLSearchParams();
  const omitted = new Set(omit);
  for (const [key, value] of Object.entries(params)) {
    if (omitted.has(key)) continue;
    if (value === undefined || value === null || value === '') continue;
    sp.set(key, String(value));
  }
  return sp;
}

export async function apiClient<T>(endpoint: string, options: FetchOptions = {}): Promise<T> {
  const { locale, requireCsrf, revalidate, ...fetchOptions } = options;
  void requireCsrf; // stripped intentionally — CSRF is not required for bearer-token API (see note above)

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    ...(fetchOptions.headers as Record<string, string>),
  };

  if (locale) {
    headers['Accept-Language'] = locale;
  }

  const url = `${API_URL}${endpoint}`;

  // Only set `next.revalidate` when explicitly requested, so authenticated
  // requests are never cached by the Next.js data cache.
  const next = revalidate !== undefined ? { revalidate } : (fetchOptions.next as Record<string, unknown> | undefined);

  const response = await fetch(url, {
    ...fetchOptions,
    ...(next ? { next } : {}),
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
