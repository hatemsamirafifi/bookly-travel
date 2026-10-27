import { render, screen, fireEvent, waitFor, act } from '@testing-library/react';
import ReviewList from '../ReviewList';

const mockFetchTourReviews = jest.fn();

jest.mock('@/lib/reviews/review-api', () => ({
  fetchTourReviews: (...args: unknown[]) => mockFetchTourReviews(...args),
}));

jest.mock('next-intl', () => {
  const translators: Record<string, (key: string, values?: Record<string, unknown>) => string> = {};
  const catalog: Record<string, Record<string, string>> = {
    reviews: {
      title: 'Reviews',
      loading: 'Loading...',
      load_more: 'Load more',
      failed_to_load: 'Failed to load reviews. Please try again later.',
      no_reviews_yet: 'No reviews yet. Be the first!',
      edited: 'Edited',
    },
    common: {
      retry: 'Try again',
      loading: 'Loading...',
      error: 'Something went wrong',
    },
    tour: {
      ratingLabel: 'Rating: {rating} out of 5',
    },
  };
  return {
    useLocale: () => 'en',
    useTranslations: (namespace?: string) => {
      const ns = namespace ?? '';
      translators[ns] ??= (key: string, values?: Record<string, unknown>) => {
        if (key === 'review_count') {
          const count = Number(values?.count ?? 0);
          return `${count} ${count === 1 ? 'review' : 'reviews'}`;
        }
        const template = catalog[ns]?.[key] ?? key;
        return Object.entries(values ?? {}).reduce(
          (result, [name, replacement]) => result.replace(`{${name}}`, String(replacement)),
          template,
        );
      };
      return translators[ns];
    },
  };
});

function pageResponse(reviews: { id: number; reviewer_name: string }[], total: number) {
  return {
    data: reviews.map((review, index) => ({
      id: review.id,
      reviewer_name: review.reviewer_name,
      rating: 4,
      comment: `Comment ${index + 1}`,
      edited: false,
      created_at: '2026-05-12T14:30:00Z',
    })),
    meta: { average_rating: 4.5, review_count: total, current_page: 1, per_page: 5, total },
  };
}

describe('ReviewList', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('shows loading state initially', () => {
    mockFetchTourReviews.mockReturnValue(new Promise(() => {}));
    render(<ReviewList tourSlug="test-tour" />);
    expect(screen.getByText('Reviews')).toBeInTheDocument();
  });

  it('renders reviews after loading', async () => {
    mockFetchTourReviews.mockResolvedValue({
      data: [
        {
          id: 1,
          reviewer_name: 'Marco',
          rating: 4,
          comment: 'Great tour!',
          edited: false,
          created_at: '2026-05-12T14:30:00Z',
        },
      ],
      meta: { average_rating: 4.0, review_count: 1, current_page: 1, per_page: 5, total: 1 },
    });

    render(<ReviewList tourSlug="test-tour" />);

    await waitFor(() => {
      expect(screen.getByText('Marco')).toBeInTheDocument();
      expect(screen.getByText('4.0')).toBeInTheDocument();
      expect(screen.getByText('May 12, 2026')).toHaveClass('text-gray-600');
    });
  });

  it('shows empty state when no reviews', async () => {
    mockFetchTourReviews.mockResolvedValue({
      data: [],
      meta: { average_rating: 0, review_count: 0, current_page: 1, per_page: 5, total: 0 },
    });

    render(<ReviewList tourSlug="empty-tour" />);

    await waitFor(() => {
      expect(screen.getByText('No reviews yet. Be the first!')).toBeInTheDocument();
    });
  });

  it('shows error state on API failure', async () => {
    mockFetchTourReviews.mockRejectedValue(new Error('Network error'));

    render(<ReviewList tourSlug="error-tour" />);

    await waitFor(() => {
      expect(screen.getByText(/Failed to load reviews/)).toBeInTheDocument();
    });
  });

  it('shows neutral loading without a zero-review claim while the first fetch is pending', () => {
    mockFetchTourReviews.mockReturnValue(new Promise(() => {}));
    render(<ReviewList tourSlug="test-tour" />);

    expect(screen.getByRole('status')).toHaveTextContent('Loading...');
    expect(screen.queryByText('No reviews yet. Be the first!')).not.toBeInTheDocument();
    expect(screen.queryByText('0 reviews')).not.toBeInTheDocument();
  });

  it('keeps loaded rows and the real aggregate when a later page fails, then retries that page', async () => {
    let failPageTwo = true;
    mockFetchTourReviews.mockImplementation((slug: string, page: number) => {
      if (page === 1) return Promise.resolve(pageResponse([{ id: 1, reviewer_name: 'Reviewer One' }], 2));
      if (failPageTwo) {
        failPageTwo = false;
        return Promise.reject(new Error('page two down'));
      }
      return Promise.resolve(pageResponse([{ id: 2, reviewer_name: 'Reviewer Two' }], 2));
    });

    render(<ReviewList tourSlug="test-tour" />);
    await waitFor(() => {
      expect(screen.getByText('Reviewer One')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => {
      expect(screen.getByText(/Failed to load reviews/)).toBeInTheDocument();
    });
    expect(screen.getByText('Reviewer One')).toBeInTheDocument();
    expect(screen.getByText('4.5')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));

    await waitFor(() => {
      expect(screen.getByText('Reviewer Two')).toBeInTheDocument();
    });
    expect(screen.getByText('Reviewer One')).toBeInTheDocument();
    expect(screen.queryByText(/Failed to load reviews/)).not.toBeInTheDocument();
  });

  it('does not duplicate rows when a retried page overlaps already loaded reviews', async () => {
    mockFetchTourReviews.mockImplementation((slug: string, page: number) =>
      page === 1
        ? Promise.resolve(pageResponse([{ id: 1, reviewer_name: 'Reviewer One' }], 2))
        : Promise.resolve(
            pageResponse(
              [
                { id: 1, reviewer_name: 'Reviewer One' },
                { id: 2, reviewer_name: 'Reviewer Two' },
              ],
              2,
            ),
          ),
    );

    render(<ReviewList tourSlug="test-tour" />);
    await waitFor(() => {
      expect(screen.getByText('Reviewer One')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => {
      expect(screen.getByText('Reviewer Two')).toBeInTheDocument();
    });
    expect(screen.getAllByText('Reviewer One')).toHaveLength(1);
  });

  it('ignores a stale response that resolves after the slug changes', async () => {
    let resolveFirst!: (value: unknown) => void;
    mockFetchTourReviews.mockImplementation((slug: string) => {
      if (slug === 'tour-a') return new Promise((resolve) => { resolveFirst = resolve; });
      return Promise.resolve(pageResponse([{ id: 9, reviewer_name: 'Reviewer B' }], 1));
    });

    const { rerender } = render(<ReviewList tourSlug="tour-a" />);
    rerender(<ReviewList tourSlug="tour-b" />);

    await waitFor(() => {
      expect(screen.getByText('Reviewer B')).toBeInTheDocument();
    });

    await act(async () => {
      resolveFirst(pageResponse([{ id: 1, reviewer_name: 'Reviewer A' }], 1));
    });

    expect(screen.queryByText('Reviewer A')).not.toBeInTheDocument();
    expect(screen.getByText('Reviewer B')).toBeInTheDocument();
  });

  it('renders review rows with localized readonly scores and edited state', async () => {
    mockFetchTourReviews.mockResolvedValue({
      data: [
        {
          id: 1,
          reviewer_name: 'Marco',
          rating: 4,
          comment: 'Great tour!',
          edited: true,
          created_at: '2026-05-12T14:30:00Z',
        },
      ],
      meta: { average_rating: 4.0, review_count: 1, current_page: 1, per_page: 5, total: 1 },
    });

    render(<ReviewList tourSlug="test-tour" />);

    await waitFor(() => {
      expect(screen.getByText('Marco')).toBeInTheDocument();
    });
    // Readonly rows use the localized display score, never the form widget.
    expect(screen.getByRole('img', { name: 'Rating: 4 out of 5' })).toBeInTheDocument();
    expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument();
    expect(screen.getByText('Edited')).toBeInTheDocument();
  });
});
