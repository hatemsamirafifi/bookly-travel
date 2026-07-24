'use client';

import { useState, useMemo, useEffect } from 'react';
import { useTranslations, useLocale } from 'next-intl';
import { useQuery } from '@tanstack/react-query';
import { Star, ChevronLeft, ChevronRight, Filter } from 'lucide-react';
import { getReviews } from '@/lib/api/partner';
import type { PartnerReview } from '@/types/partner';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import ErrorState from '@/components/ui/ErrorState';
import EmptyState from '@/components/ui/EmptyState';
import { ReviewResponseForm } from './ReviewResponseForm';

const PER_PAGE = 10;

interface ReviewListProps {
  /** Optional tour filter passed from parent */
  tourFilter?: string | number | null;
  /** Optional rating filter */
  ratingFilter?: number | null;
  /** Optional response status filter: 'with' | 'without' | null */
  responseFilter?: 'with' | 'without' | null;
}

export function ReviewList({ tourFilter, ratingFilter, responseFilter }: ReviewListProps) {
  const t = useTranslations('partner.reviews');
  const locale = useLocale();
  const [page, setPage] = useState(1);
  const [respondingId, setRespondingId] = useState<string | number | null>(null);

  // Reset to the first page whenever an external filter changes
  useEffect(() => {
    setPage(1);
  }, [tourFilter, ratingFilter, responseFilter]);

  const {
    data,
    isLoading,
    error,
    refetch,
  } = useQuery({
    queryKey: ['partner-reviews', page],
    queryFn: () => getReviews(page),
    staleTime: 60_000,
    retry: 1,
  });

  const reviews = useMemo(() => {
    let items = data?.data ?? [];
    // Client-side filtering (API may not support all filter types)
    if (tourFilter) {
      items = items.filter((r) => r.tour_slug === String(tourFilter));
    }
    if (ratingFilter) {
      items = items.filter((r) => r.rating === ratingFilter);
    }
    if (responseFilter === 'with') {
      items = items.filter((r) => !!r.response);
    } else if (responseFilter === 'without') {
      items = items.filter((r) => !r.response);
    }
    return items;
  }, [data, tourFilter, ratingFilter, responseFilter]);

  const totalPages = data?.meta?.last_page ?? 1;

  const formatDate = (isoDate: string) =>
    new Date(isoDate).toLocaleDateString(locale, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });

  if (isLoading) {
    return (
      <div className="space-y-4">
        {Array.from({ length: 3 }).map((_, i) => (
          <LoadingSkeleton key={i} variant="card" />
        ))}
      </div>
    );
  }

  if (error) {
    return <ErrorState message={t('loadError')} retryLabel={t('retry')} onRetry={() => refetch()} />;
  }

  if (reviews.length === 0) {
    return (
      <EmptyState
        title={t('noReviews')}
        icon={<Filter className="h-6 w-6" />}
      />
    );
  }

  return (
    <div className="space-y-4">
      {reviews.map((review) => (
        <div key={review.id} className="bg-white rounded-xl border border-gray-200 p-5 transition-shadow hover:shadow-sm">
          {/* Header: traveler info + tour name */}
          <div className="flex items-start justify-between mb-3">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <span className="font-semibold text-[#0A2540]">{review.reviewer_name}</span>
                <span className="text-xs text-gray-400">• {t('verifiedTraveler')}</span>
              </div>
              <p className="text-xs text-gray-500 mb-1">{review.tour_title}</p>
              <div className="flex items-center gap-0.5">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Star
                    key={i}
                    className={`w-4 h-4 ${
                      i < review.rating
                        ? 'text-[#FFB800] fill-[#FFB800]'
                        : 'text-gray-200'
                    }`}
                  />
                ))}
              </div>
            </div>
            <span className="text-xs text-gray-400">
              {formatDate(review.created_at)}
            </span>
          </div>

          {/* Review text */}
          <p className="text-sm text-gray-700 mb-4">{review.comment}</p>

          {/* Response section — always use ReviewResponseForm */}
          {respondingId === review.id ? (
            <ReviewResponseForm
              review={review}
              onSuccess={() => {
                setRespondingId(null);
                refetch();
              }}
            />
          ) : review.response ? (
            /* Show existing response */
            <div className="bg-gray-50 rounded-lg p-3 border border-gray-100">
              <div className="flex items-center justify-between mb-1">
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">{t('yourResponse')}</p>
                <span className="text-xs text-gray-400">
                  {t('respondedOn', { date: formatDate(review.response.created_at) })}
                  {review.response.updated_at && review.response.updated_at !== review.response.created_at && (
                    <> &middot; {t('editedOn', { date: formatDate(review.response.updated_at) })}</>
                  )}
                </span>
              </div>
              <p className="text-sm text-gray-700">{review.response.response_text}</p>
              <button
                type="button"
                className="mt-2 text-xs font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                onClick={() => setRespondingId(review.id)}
              >
                {t('editResponse')}
              </button>
            </div>
          ) : (
            /* No response yet — show respond button */
            <button
              type="button"
              className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
              onClick={() => setRespondingId(review.id)}
            >
              {t('respond')}
            </button>
          )}
        </div>
      ))}

      {/* Pagination controls */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-3 pt-4">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            aria-label={t('previousPage')}
          >
            <ChevronLeft className="w-4 h-4" />
          </button>
          <span className="text-sm text-gray-500">
            {page} / {totalPages}
          </span>
          <button
            type="button"
            disabled={page >= totalPages}
            onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            aria-label={t('nextPage')}
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      )}
    </div>
  );
}
