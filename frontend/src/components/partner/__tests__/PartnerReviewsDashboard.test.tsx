import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import PartnerReviewsDashboard from '../reviews/PartnerReviewsDashboard';
import type { PartnerReview, PaginatedPartnerResponse } from '@/types/partner';

jest.mock('@/lib/api/partner', () => ({
  ...jest.requireActual('@/lib/api/partner'),
  getReviews: jest.fn(),
}));

jest.mock('@/components/partner/reviews/ReviewResponseForm', () => ({
  ReviewResponseForm: () => <div data-testid="response-form" />,
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
  return function MockErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
    return (
      <div data-testid="error-state">
        <span>{message}</span>
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

jest.mock('next-intl', () => ({
  useTranslations: () => (key: string, values?: Record<string, unknown>) => {
    if (key === 'reviewsCount') {
      const count = (values?.count as number) ?? 0;
      return `${count} ${count === 1 ? 'review' : 'reviews'}`;
    }
    return key;
  },
  useLocale: () => 'en',
}));

jest.mock('next/navigation', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

const { getReviews } = jest.requireMock('@/lib/api/partner') as {
  getReviews: jest.Mock;
};

function makeReview(overrides: Partial<PartnerReview> = {}): PartnerReview {
  return {
    id: 1,
    tour_slug: 'tuscan-wine',
    tour_title: 'Tuscan Wine Tasting',
    reviewer_name: 'Alice',
    rating: 5,
    comment: 'Amazing tour!',
    status: 'visible',
    created_at: '2026-08-01T10:00:00Z',
    response: null,
    ...overrides,
  } as PartnerReview;
}

function makeResponse(data: PartnerReview[], lastPage = 1): PaginatedPartnerResponse<PartnerReview> {
  return {
    data,
    meta: {
      current_page: 1,
      last_page: lastPage,
      per_page: 10,
      total: data.length,
      unread_count: 0,
    },
  };
}

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, staleTime: 0, gcTime: 0 } },
  });
  return render(
    <QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>
  );
}

describe('PartnerReviewsDashboard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('shows loading skeleton while fetching', () => {
    getReviews.mockReturnValue(new Promise(() => {}));
    renderWithClient(<PartnerReviewsDashboard />);
    expect(screen.getByTestId('loading-skeleton')).toBeInTheDocument();
  });

  it('shows empty state when no reviews exist', async () => {
    getReviews.mockResolvedValue(makeResponse([]));
    renderWithClient(<PartnerReviewsDashboard />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows error state when the API fails', async () => {
    getReviews.mockRejectedValue(new Error('Network error'));
    renderWithClient(<PartnerReviewsDashboard />);
    await waitFor(
      () => {
        expect(screen.getByTestId('error-state')).toBeInTheDocument();
      },
      { timeout: 5000 }
    );
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('renders tour summaries and expands a tour to show reviews', async () => {
    const reviews: PartnerReview[] = [
      makeReview({ id: 1, rating: 5, tour_slug: 'tuscan-wine', tour_title: 'Tuscan Wine Tasting' }),
      makeReview({ id: 2, rating: 3, tour_slug: 'tuscan-wine', tour_title: 'Tuscan Wine Tasting', reviewer_name: 'Bob' }),
      makeReview({ id: 3, rating: 4, tour_slug: 'rome-walk', tour_title: 'Rome Walking Tour', reviewer_name: 'Carol' }),
    ];
    getReviews.mockResolvedValue(makeResponse(reviews));
    renderWithClient(<PartnerReviewsDashboard />);

    await waitFor(() => {
      expect(screen.getAllByText('Tuscan Wine Tasting').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Rome Walking Tour').length).toBeGreaterThan(0);
    });

    const summaryButtons = screen.getAllByText('Tuscan Wine Tasting').map(
      (el) => el.closest('button')
    ).filter(Boolean) as HTMLElement[];
    fireEvent.click(summaryButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('filters reviews by rating', async () => {
    const reviews: PartnerReview[] = [
      makeReview({ id: 1, rating: 5, reviewer_name: 'Alice' }),
      makeReview({ id: 2, rating: 3, reviewer_name: 'Bob' }),
    ];
    getReviews.mockResolvedValue(makeResponse(reviews));
    renderWithClient(<PartnerReviewsDashboard />);

    await waitFor(() => {
      expect(screen.getAllByText('Tuscan Wine Tasting').length).toBeGreaterThan(0);
    });

    const ratingSelect = screen.getByLabelText('reviews.filterByRating') as HTMLSelectElement;
    fireEvent.change(ratingSelect, { target: { value: '3' } });

    const summaryButtons = screen.getAllByText('Tuscan Wine Tasting').map(
      (el) => el.closest('button')
    ).filter(Boolean) as HTMLElement[];
    fireEvent.click(summaryButtons[0]);

    await waitFor(() => {
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });
});