'use client';

import { useEffect, useState, useCallback, useRef } from 'react';
import { useTranslations } from 'next-intl';
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
  const t = useTranslations('reviews');
  const tc = useTranslations('common');
  const [reviews, setReviews] = useState<ReviewData[]>([]);
  const [averageRating, setAverageRating] = useState<number | null>(null);
  const [reviewCount, setReviewCount] = useState(0);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [loadMoreLoading, setLoadMoreLoading] = useState(false);
  const [error, setError] = useState('');
  const [pageError, setPageError] = useState('');
  const [failedPage, setFailedPage] = useState<number | null>(null);
  const [firstLoaded, setFirstLoaded] = useState(false);
  const requestRef = useRef(0);
  const firstLoadedRef = useRef(false);

  const loadReviews = useCallback(async (pageNum: number) => {
    const requestId = ++requestRef.current;
    const isFirstPage = pageNum === 1;
    const isInitialLoad = isFirstPage && !firstLoadedRef.current;
    if (isInitialLoad) setLoading(true);
    else setLoadMoreLoading(true);

    try {
      const result = await fetchTourReviews(tourSlug, pageNum);
      if (requestRef.current !== requestId) return;
      setReviews((prev) => {
        if (isFirstPage) return result.data;
        const knownIds = new Set(prev.map((review) => review.id));
        return [...prev, ...result.data.filter((review) => !knownIds.has(review.id))];
      });
      setAverageRating(result.meta.average_rating > 0 ? result.meta.average_rating : null);
      setReviewCount(result.meta.review_count);
      setTotal(result.meta.total);
      setPage(pageNum);
      if (isFirstPage) {
        firstLoadedRef.current = true;
        setFirstLoaded(true);
        setError('');
      } else {
        setPageError('');
        setFailedPage(null);
      }
    } catch {
      if (requestRef.current !== requestId) return;
      if (isFirstPage && !firstLoadedRef.current) {
        setError(t('failed_to_load'));
      } else {
        setPageError(t('failed_to_load'));
        setFailedPage(pageNum);
      }
    } finally {
      if (requestRef.current === requestId) {
        setLoading(false);
        setLoadMoreLoading(false);
      }
    }
  }, [tourSlug, t]);

  useEffect(() => {
    setReviews([]);
    setAverageRating(null);
    setReviewCount(0);
    setPage(1);
    setTotal(0);
    setError('');
    setPageError('');
    setFailedPage(null);
    setFirstLoaded(false);
    firstLoadedRef.current = false;
    loadReviews(1);
  }, [loadReviews]);

  useEffect(() => () => {
    requestRef.current += 1;
  }, []);

  const isFirstLoading = loading && !firstLoaded && reviews.length === 0;
  const hasMore = reviews.length < total;
  const showAggregate = !isFirstLoading && !(error && reviews.length === 0) && (reviews.length > 0 || averageRating !== null);

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      <h3 className="mb-3 text-sm font-semibold text-gray-700">{t('title')}</h3>

      {isFirstLoading ? (
        <>
          <p role="status" className="text-sm text-gray-500">{t('loading')}</p>
          <div className="space-y-3">
            {[1, 2].map((n) => (
              <div key={n} className="animate-pulse border-b border-gray-100 py-3">
                <div className="h-3 bg-gray-200 rounded w-24 mb-2" />
                <div className="h-2 bg-gray-200 rounded w-32 mb-1" />
                <div className="h-2 bg-gray-200 rounded w-full" />
              </div>
            ))}
          </div>
        </>
      ) : (
        <>
          {showAggregate && (
            <div className="mb-4">
              <AggregateRating averageRating={averageRating} reviewCount={reviewCount} />
            </div>
          )}

          {error && reviews.length === 0 && (
            <>
              <div role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</div>
              <div className="mt-3 text-center">
                <button
                  type="button"
                  onClick={() => loadReviews(1)}
                  className="text-sm text-primary font-medium underline"
                >
                  {tc('retry')}
                </button>
              </div>
            </>
          )}

          {!error && reviews.length === 0 && (
            <p className="text-sm text-gray-500 text-center py-4">{t('no_reviews_yet')}</p>
          )}

          {reviews.length > 0 && (
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

              {pageError && (
                <>
                  <div role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{pageError}</div>
                  <div className="mt-3 text-center">
                    <button
                      type="button"
                      onClick={() => { if (failedPage !== null) loadReviews(failedPage); }}
                      className="text-sm text-primary font-medium underline"
                    >
                      {tc('retry')}
                    </button>
                  </div>
                </>
              )}

              {!pageError && hasMore && (
                <div className="mt-3 text-center">
                  <button
                    onClick={() => loadReviews(page + 1)}
                    disabled={loadMoreLoading}
                    className="text-sm text-[#0A2540] hover:text-[#071b2e] disabled:opacity-50 font-medium underline"
                  >
                    {loadMoreLoading ? t('loading') : t('load_more')}
                  </button>
                </div>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}
