import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import AdminReviewPanel from '../AdminReviewPanel';
import {
  fetchAdminReviews,
  hideReview,
  reinstateReview,
} from '@/lib/reviews/review-api';
import type { AdminReview, AdminReviewsResponse } from '@/lib/reviews/review-api';

jest.mock('@/lib/reviews/review-api', () => ({
  fetchAdminReviews: jest.fn(),
  hideReview: jest.fn(),
  reinstateReview: jest.fn(),
  apiClient: jest.fn(),
}));

jest.mock('@/components/reviews/StarRating', () => {
  return function MockStarRating({ value }: { value: number }) {
    return <span aria-label={`Rating ${value}`}>{'★'.repeat(value)}</span>;
  };
});

jest.mock('@/components/ui/EmptyState', () => {
  return function MockEmptyState({ title }: { title: string }) {
    return <div data-testid="empty-state">{title}</div>;
  };
});

jest.mock('@/components/ui/ErrorState', () => {
  return function MockErrorState({ onRetry }: { message: string; onRetry: () => void }) {
    return (
      <div data-testid="error-state">
        <button onClick={onRetry}>Try Again</button>
      </div>
    );
  };
});

jest.mock('@/components/ui/LoadingSkeleton', () => {
  return function MockLoadingSkeleton() {
    return <div data-testid="loading-skeleton" />;
  };
});

jest.mock('@/components/ui/Toast', () => {
  return function MockToast({ message, onClose }: { message: string; type: string; onClose: () => void }) {
    return (
      <div data-testid="toast">
        <span>{message}</span>
        <button onClick={onClose}>Close</button>
      </div>
    );
  };
});

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string) => key,
}));

const mockedFetchAdminReviews = fetchAdminReviews as jest.MockedFunction<typeof fetchAdminReviews>;
const mockedHideReview = hideReview as jest.MockedFunction<typeof hideReview>;

function makeReview(overrides: Partial<AdminReview> = {}): AdminReview {
  return {
    id: 1,
    reviewer_name: 'Alice',
    rating: 5,
    comment: 'Great tour!',
    status: 'visible',
    tour_id: 10,
    tour_title: 'Tuscan Wine Tasting',
    flagged: false,
    created_at: '2026-08-01T10:00:00Z',
    audit_trail: [
      { action: 'submitted', created_at: '2026-08-01T10:00:00Z' },
    ],
    ...overrides,
  };
}

function makeResponse(reviews: AdminReview[], lastPage = 1): AdminReviewsResponse {
  return {
    data: reviews,
    meta: { current_page: 1, last_page: lastPage, per_page: 20, total: reviews.length },
  };
}

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, staleTime: 0 } },
  });
  return render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
  );
}

describe('AdminReviewPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('shows loading skeleton while fetching', () => {
    mockedFetchAdminReviews.mockReturnValue(new Promise(() => {}));
    renderWithClient(<AdminReviewPanel />);
    expect(screen.getByTestId('loading-skeleton')).toBeInTheDocument();
  });

  it('shows empty state when no reviews exist', async () => {
    mockedFetchAdminReviews.mockResolvedValue(makeResponse([]));
    renderWithClient(<AdminReviewPanel />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows error state when the API fails', async () => {
    mockedFetchAdminReviews.mockRejectedValue(new Error('Network error'));
    renderWithClient(<AdminReviewPanel />);
    await waitFor(
      () => {
        expect(screen.getByTestId('error-state')).toBeInTheDocument();
      },
      { timeout: 5000 }
    );
  });

  it('renders reviews with status badges and audit trail', async () => {
    const reviews = [
      makeReview({ id: 1, status: 'visible', reviewer_name: 'Alice' }),
      makeReview({ id: 2, status: 'hidden', reviewer_name: 'Bob', comment: 'Hidden review' }),
    ];
    mockedFetchAdminReviews.mockResolvedValue(makeResponse(reviews));
    renderWithClient(<AdminReviewPanel />);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });

    expect(screen.getAllByText('submitted').length).toBeGreaterThan(0);
  });

  it('shows hide button for visible reviews and opens confirmation modal', async () => {
    mockedFetchAdminReviews.mockResolvedValue(makeResponse([makeReview({ status: 'visible' })]));
    renderWithClient(<AdminReviewPanel />);

    await waitFor(() => {
      expect(screen.getByText('hide')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('hide'));

    await waitFor(() => {
      expect(screen.getByText('hideConfirmTitle')).toBeInTheDocument();
      expect(screen.getByText('reasonLabel')).toBeInTheDocument();
    });
  });

  it('shows reinstate button for hidden reviews', async () => {
    mockedFetchAdminReviews.mockResolvedValue(makeResponse([makeReview({ status: 'hidden' })]));
    renderWithClient(<AdminReviewPanel />);

    await waitFor(() => {
      expect(screen.getByText('reinstate')).toBeInTheDocument();
    });
  });

  it('hides a review after confirming with a reason', async () => {
    mockedFetchAdminReviews.mockResolvedValue(makeResponse([makeReview({ id: 42, status: 'visible' })]));
    mockedHideReview.mockResolvedValue({} as never);
    renderWithClient(<AdminReviewPanel />);

    await waitFor(() => {
      expect(screen.getByText('hide')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByText('hide'));

    const reasonInput = await screen.findByPlaceholderText('reasonPlaceholder');
    fireEvent.change(reasonInput, { target: { value: 'Inappropriate content' } });

    fireEvent.click(screen.getByText('confirm'));

    await waitFor(() => {
      expect(mockedHideReview).toHaveBeenCalledWith(42, 'Inappropriate content');
    });
  });

  it('renders pagination controls when multiple pages exist', async () => {
    mockedFetchAdminReviews.mockResolvedValue(makeResponse([makeReview()], 3));
    renderWithClient(<AdminReviewPanel />);

    await waitFor(() => {
      expect(screen.getByText('previous')).toBeInTheDocument();
      expect(screen.getByText('next')).toBeInTheDocument();
    });
  });
});