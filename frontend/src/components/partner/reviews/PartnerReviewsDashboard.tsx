'use client';

import { useMemo, useState } from 'react';
import { useTranslations, useLocale } from 'next-intl';
import { useQuery } from '@tanstack/react-query';
import {
  ChevronDown,
  ChevronUp,
  ChevronLeft,
  ChevronRight,
  MessageSquare,
  Star,
} from 'lucide-react';
import { getReviews } from '@/lib/api/partner';
import type { PartnerReview } from '@/types/partner';
import StarRating from '@/components/reviews/StarRating';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import { ReviewResponseForm } from '@/components/partner/reviews/ReviewResponseForm';

const PER_PAGE = 20;

interface TourSummary {
  tourId: string | number;
  tourTitle: string;
  average: number;
  count: number;
  reviews: PartnerReview[];
}

type ResponseFilter = 'all' | 'with' | 'without';

export default function PartnerReviewsDashboard() {
  const t = useTranslations('partner');
  const locale = useLocale();

  const [page, setPage] = useState(1);
  const [expandedTourId, setExpandedTourId] = useState<string | number | null>(null);
  const [respondingReviewId, setRespondingReviewId] = useState<string | number | null>(null);

  // Filters
  const [selectedTourId, setSelectedTourId] = useState<string>('');
  const [selectedRating, setSelectedRating] = useState<string>('');
  const [selectedResponseStatus, setSelectedResponseStatus] = useState<ResponseFilter>('all');

  const {
    data,
    isLoading,
    error,
    refetch,
  } = useQuery({
    queryKey: ['partner-reviews-dashboard', page],
    queryFn: () => getReviews(page),
    staleTime: 60_000,
    retry: 1,
  });

  const allReviews = useMemo(() => data?.data ?? [], [data]);
  const totalPages = data?.meta?.last_page ?? 1;

  // Extract unique tours for the tour filter dropdown
  const tourOptions = useMemo(() => {
    const map = new Map<string, string>();
    allReviews.forEach((r) => map.set(String(r.tour.id), r.tour.title));
    return Array.from(map.entries()).map(([id, title]) => ({ id, title }));
  }, [allReviews]);

  // Apply client-side filters
  const filteredReviews = useMemo(() => {
    let items = allReviews;
    if (selectedTourId) {
      items = items.filter((r) => String(r.tour.id) === selectedTourId);
    }
    if (selectedRating) {
      items = items.filter((r) => r.rating === Number(selectedRating));
    }
    if (selectedResponseStatus === 'with') {
      items = items.filter((r) => !!r.response);
    } else if (selectedResponseStatus === 'without') {
      items = items.filter((r) => !r.response);
    }
    return items;
  }, [allReviews, selectedTourId, selectedRating, selectedResponseStatus]);

  // Build tour summaries from filtered reviews
  const tourSummaries: TourSummary[] = useMemo(() => {
    const map = new Map<string | number, TourSummary>();
    filteredReviews.forEach((r) => {
      const existing = map.get(r.tour.id);
      if (existing) {
        existing.reviews.push(r);
        existing.count = existing.reviews.length;
        existing.average =
          existing.reviews.reduce((sum, rv) => sum + rv.rating, 0) / existing.reviews.length;
      } else {
        map.set(r.tour.id, {
          tourId: r.tour.id,
          tourTitle: r.tour.title,
          average: r.rating,
          count: 1,
          reviews: [r],
        });
      }
    });
    return Array.from(map.values()).sort((a, b) => b.count - a.count);
  }, [filteredReviews]);

  const hasActiveFilters = !!selectedTourId || !!selectedRating || selectedResponseStatus !== 'all';

  const clearFilters = () => {
    setSelectedTourId('');
    setSelectedRating('');
    setSelectedResponseStatus('all');
  };

  const formatDate = (isoDate: string) =>
    new Date(isoDate).toLocaleDateString(locale, {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });

  if (isLoading) {
    return <LoadingSkeleton variant="list" count={4} />;
  }

  if (error) {
    return (
      <ErrorState
        message={t('reviews.loadError')}
        onRetry={() => refetch()}
      />
    );
  }

  if (allReviews.length === 0) {
    return (
      <EmptyState
        title={t('reviews.noReviews')}
        icon={<MessageSquare className="h-6 w-6" />}
      />
    );
  }

  return (
    <div className="space-y-6">
      {/* Filters */}
      <section className="flex flex-wrap items-end gap-3" aria-label="Filters">
        {/* Tour filter */}
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-tour" className="text-xs font-medium text-gray-500">
            {t('reviews.filterByTour')}
          </label>
          <select
            id="filter-tour"
            value={selectedTourId}
            onChange={(e) => setSelectedTourId(e.target.value)}
            className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
          >
            <option value="">{t('reviews.allTours')}</option>
            {tourOptions.map((opt) => (
              <option key={opt.id} value={opt.id}>{opt.title}</option>
            ))}
          </select>
        </div>

        {/* Rating filter */}
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-rating" className="text-xs font-medium text-gray-500">
            {t('reviews.filterByRating')}
          </label>
          <select
            id="filter-rating"
            value={selectedRating}
            onChange={(e) => setSelectedRating(e.target.value)}
            className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
          >
            <option value="">{t('reviews.allTours').replace(t('reviews.allTours'), '★ All')}</option>
            {[5, 4, 3, 2, 1].map((r) => (
              <option key={r} value={r}>{'★'.repeat(r)} ({r})</option>
            ))}
          </select>
        </div>

        {/* Response status filter */}
        <div className="flex flex-col gap-1">
          <label htmlFor="filter-response" className="text-xs font-medium text-gray-500">
            {t('reviews.filterByResponse')}
          </label>
          <select
            id="filter-response"
            value={selectedResponseStatus}
            onChange={(e) => setSelectedResponseStatus(e.target.value as ResponseFilter)}
            className="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
          >
            <option value="all">{t('reviews.allTours').replace(t('reviews.allTours'), 'All')}</option>
            <option value="with">{t('reviews.withResponse')}</option>
            <option value="without">{t('reviews.withoutResponse')}</option>
          </select>
        </div>

        {/* Clear filters */}
        {hasActiveFilters && (
          <button
            type="button"
            onClick={clearFilters}
            className="text-xs font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors self-end pb-1.5"
          >
            ✕ Clear
          </button>
        )}
      </section>

      {/* Tour summary cards */}
      <section aria-label={t('reviews.tourSummary')}>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
          {t('reviews.tourSummary')}
        </h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {tourSummaries.map((summary) => (
            <button
              key={summary.tourId}
              onClick={() =>
                setExpandedTourId((prev) => (prev === summary.tourId ? null : summary.tourId))
              }
              className={`rounded-lg border bg-white p-4 text-left transition-all hover:shadow-sm ${
                expandedTourId === summary.tourId
                  ? 'border-[#FFB800] ring-1 ring-[#FFB800]'
                  : 'border-gray-200'
              }`}
            >
              <p className="truncate text-sm font-semibold text-gray-900">{summary.tourTitle}</p>
              <div className="mt-2 flex items-center gap-3">
                <span className="text-2xl font-bold text-[#0A2540]">
                  {summary.average.toFixed(1)}
                </span>
                <div>
                  <StarRating value={Math.round(summary.average)} readOnly size="sm" />
                  <p className="mt-0.5 text-xs text-gray-500">
                    {t('reviews.reviewsCount', { count: summary.count })}
                  </p>
                </div>
              </div>
            </button>
          ))}
        </div>
      </section>

      {/* Expandable per-tour review lists */}
      <section>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
          {t('reviews.title')}
        </h2>
        <div className="space-y-3">
          {tourSummaries.map((summary) => {
            const isExpanded = expandedTourId === summary.tourId;
            return (
              <div
                key={summary.tourId}
                className="rounded-lg border border-gray-200 bg-white"
              >
                <button
                  onClick={() => setExpandedTourId(isExpanded ? null : summary.tourId)}
                  className="flex w-full items-center justify-between p-4 text-left"
                  aria-expanded={isExpanded}
                >
                  <div>
                    <p className="text-sm font-semibold text-gray-900">{summary.tourTitle}</p>
                    <div className="mt-0.5 flex items-center gap-2">
                      <div className="flex items-center gap-0.5">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <Star
                            key={i}
                            className={`w-3 h-3 ${
                              i < Math.round(summary.average)
                                ? 'text-[#FFB800] fill-[#FFB800]'
                                : 'text-gray-200'
                            }`}
                          />
                        ))}
                      </div>
                      <span className="text-xs text-gray-500">
                        {summary.average.toFixed(1)} · {t('reviews.reviewsCount', { count: summary.count })}
                      </span>
                    </div>
                  </div>
                  {isExpanded ? (
                    <ChevronUp className="h-5 w-5 text-gray-400" />
                  ) : (
                    <ChevronDown className="h-5 w-5 text-gray-400" />
                  )}
                </button>
                {isExpanded && (
                  <div className="border-t border-gray-100 px-4 pb-4">
                    <div className="mt-3 space-y-4">
                      {summary.reviews.map((review) => (
                        <div key={review.id} className="border-b border-gray-50 pb-4 last:border-b-0 last:pb-0">
                          {/* Review header */}
                          <div className="flex items-start justify-between mb-1">
                            <div>
                              <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-gray-800">
                                  {review.traveler_name}
                                </span>
                                <span className="text-xs text-gray-400">
                                  • {t('reviews.verifiedTraveler')}
                                </span>
                              </div>
                              <div className="flex items-center gap-1 mt-0.5">
                                {Array.from({ length: 5 }).map((_, i) => (
                                  <Star
                                    key={i}
                                    className={`w-3.5 h-3.5 ${
                                      i < review.rating
                                        ? 'text-[#FFB800] fill-[#FFB800]'
                                        : 'text-gray-200'
                                    }`}
                                  />
                                ))}
                              </div>
                            </div>
                            <div className="flex items-center gap-2">
                              {review.response ? (
                                <span className="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-green-50 text-green-700">
                                  {t('reviews.hasResponse')}
                                </span>
                              ) : (
                                <span className="inline-flex items-center text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-amber-50 text-amber-700">
                                  {t('reviews.noResponse')}
                                </span>
                              )}
                              <span className="text-xs text-gray-400">
                                {formatDate(review.submitted_at)}
                              </span>
                            </div>
                          </div>

                          {/* Review text */}
                          {review.text && (
                            <p className="text-sm text-gray-600 leading-relaxed mt-1 mb-3">
                              {review.text}
                            </p>
                          )}

                          {/* Response form or existing response display */}
                          <div className="mt-2">
                            {respondingReviewId === review.id ? (
                              <ReviewResponseForm
                                review={review}
                                onSuccess={() => {
                                  setRespondingReviewId(null);
                                  refetch();
                                }}
                              />
                            ) : review.response ? (
                              /* Show existing response with metadata */
                              <div className="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <div className="flex items-center justify-between mb-1">
                                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    {t('reviews.yourResponse')}
                                  </p>
                                  <span className="text-xs text-gray-400">
                                    {t('reviews.respondedOn', { date: formatDate(review.response.created_at) })}
                                    {review.response.updated_at && review.response.updated_at !== review.response.created_at && (
                                      <> &middot; {t('reviews.editedOn', { date: formatDate(review.response.updated_at) })}</>
                                    )}
                                  </span>
                                </div>
                                <p className="text-sm text-gray-700">{review.response.response_text}</p>
                                <button
                                  type="button"
                                  onClick={() => setRespondingReviewId(review.id)}
                                  className="mt-2 text-xs font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                                >
                                  {t('reviews.editResponse')}
                                </button>
                              </div>
                            ) : (
                              /* No response — show respond button */
                              <button
                                type="button"
                                onClick={() => setRespondingReviewId(review.id)}
                                className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                              >
                                {t('reviews.respond')}
                              </button>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </section>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex items-center justify-center gap-3 pt-2">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            aria-label="Previous page"
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
            aria-label="Next page"
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Empty state when filters produce no results */}
      {hasActiveFilters && filteredReviews.length === 0 && (
        <div className="text-center py-8">
          <p className="text-sm text-gray-500">{t('reviews.noReviews')}</p>
          <button
            type="button"
            onClick={clearFilters}
            className="mt-2 text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
          >
            Clear filters
          </button>
        </div>
      )}
    </div>
  );
}
