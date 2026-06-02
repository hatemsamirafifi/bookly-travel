const API_BASE = process.env.NEXT_PUBLIC_API_URL || '';

interface SubmitReviewPayload {
  booking_reference: string;
  rating: number;
  comment?: string;
  locale: string;
}

interface EditReviewPayload {
  rating: number;
  comment?: string;
}

interface ReviewResponse {
  data: {
    id: number;
    reviewer_name: string;
    rating: number;
    comment?: string | null;
    status: string;
    edited: boolean;
    created_at: string;
    updated_at?: string;
  };
}

interface TourReviewsResponse {
  data: ReviewResponse['data'][];
  meta: {
    average_rating: number;
    review_count: number;
    current_page: number;
    per_page: number;
    total: number;
  };
}

async function apiFetch(url: string, options?: RequestInit) {
  const token = typeof window !== 'undefined' ? localStorage.getItem('sanctum_token') : null;

  const res = await fetch(`${API_BASE}/api${url}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...options?.headers,
    },
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    const message =
      body?.error?.message || body?.message || `Request failed with status ${res.status}`;
    const error = new Error(message);
    (error as Error & { status: number }).status = res.status;
    throw error;
  }

  return res.json();
}

export async function submitReview(payload: SubmitReviewPayload): Promise<ReviewResponse> {
  return apiFetch('/public/reviews', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function editReview(reviewId: number, payload: EditReviewPayload): Promise<ReviewResponse> {
  return apiFetch(`/public/reviews/${reviewId}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export async function fetchTourReviews(
  tourSlug: string,
  page = 1,
  perPage = 5,
): Promise<TourReviewsResponse> {
  return apiFetch(
    `/public/tours/${tourSlug}/reviews?page=${page}&per_page=${perPage}`,
  );
}
