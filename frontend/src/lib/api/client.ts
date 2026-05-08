const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost/api';

interface FetchOptions extends RequestInit {
  locale?: string;
}

export async function apiClient<T>(endpoint: string, options: FetchOptions = {}): Promise<T> {
  const { locale, ...fetchOptions } = options;

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

    if (response.status === 410) {
      const body = await response.json().catch(() => ({}));
      throw new GoneError(body.message || 'This resource is no longer available.');
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
