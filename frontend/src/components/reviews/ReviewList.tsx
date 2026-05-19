'use client';

import { useEffect, useState, useCallback } from 'react';
import AggregateRating from './AggregateRating';
import ReviewCard from './ReviewCard';
import { fetchTourReviews } from '@/lib/reviews/review-api';

interface ReviewListProps {
  tourSlug: string;
  locale?: string;
}

interface ReviewData {
  id: number;
  reviewer_name: string;
  rating: number;
  comment?: string | null;
  edited: boolean;
  created_at: string;
}

export default function ReviewList({ tourSlug, locale = 'en' }: ReviewListProps) {
  const [reviews, setReviews] = useState<ReviewData[]>([]);
  const [averageRating, setAverageRating] = useState<number | null>(null);
  const [reviewCount, setReviewCount] = useState(0);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadMoreLoading, setLoadMoreLoading] = useState(false);
  const [error, setError] = useState('');

  const loadReviews = useCallback(async (pageNum: number) => {
    const isFirstPage = pageNum === 1;
    if (isFirstPage) setLoading(true);
    else setLoadMoreLoading(true);

    try {
      const result = await fetchTourReviews(tourSlug, pageNum);
      if (isFirstPage) {
        setReviews(result.data);
      } else {
        setReviews((prev) => [...prev, ...result.data]);
      }
      setAverageRating(result.meta.average_rating > 0 ? result.meta.average_rating : null);
      setReviewCount(result.meta.review_count);
      setTotal(result.meta.total);
      setPage(pageNum);
      setError('');
    } catch {
      setError('Failed to load reviews. Please try again later.');
    } finally {
      setLoading(false);
      setLoadMoreLoading(false);
    }
  }, [tourSlug]);

  useEffect(() => {
    loadReviews(1);
  }, [loadReviews]);

  const hasMore = reviews.length < total;

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">Reviews</h3>

      <div className="mb-4">
        <AggregateRating averageRating={averageRating} reviewCount={reviewCount} />
      </div>

      {loading && (
        <div className="space-y-3">
          {[1, 2].map((n) => (
            <div key={n} className="animate-pulse border-b border-gray-100 py-3">
              <div className="h-3 bg-gray-200 rounded w-24 mb-2" />
              <div className="h-2 bg-gray-200 rounded w-32 mb-1" />
              <div className="h-2 bg-gray-200 rounded w-full" />
            </div>
          ))}
        </div>
      )}

      {error && (
        <div className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</div>
      )}

      {!loading && !error && reviews.length === 0 && (
        <p className="text-sm text-gray-400 text-center py-4">No reviews yet. Be the first!</p>
      )}

      {!loading && !error && reviews.length > 0 && (
        <>
          <div>
            {reviews.map((review) => (
              <ReviewCard
                key={review.id}
                reviewerName={review.reviewer_name}
                rating={review.rating}
                comment={review.comment}
                edited={review.edited}
                createdAt={review.created_at}
                locale={locale}
              />
            ))}
          </div>

          {hasMore && (
            <div className="mt-3 text-center">
              <button
                onClick={() => loadReviews(page + 1)}
                disabled={loadMoreLoading}
                className="text-sm text-[#0A2540] hover:text-[#071b2e] disabled:opacity-50 font-medium underline"
              >
                {loadMoreLoading ? 'Loading...' : 'Load More'}
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
