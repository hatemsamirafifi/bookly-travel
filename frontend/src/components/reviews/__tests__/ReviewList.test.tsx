import { render, screen, waitFor } from '@testing-library/react';
import ReviewList from '../ReviewList';

const mockFetchTourReviews = jest.fn();

jest.mock('@/lib/reviews/review-api', () => ({
  fetchTourReviews: (...args: unknown[]) => mockFetchTourReviews(...args),
}));

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
});
