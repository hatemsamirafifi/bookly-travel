'use client';

import { useMemo, useState } from 'react';
import { useTranslations } from 'next-intl';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ChevronDown, ChevronUp, MessageSquare } from 'lucide-react';
import { fetchPartnerReviews } from '@/lib/reviews/review-api';
import type { PartnerReview } from '@/types/partner';
import ReviewCard from '@/components/reviews/ReviewCard';
import StarRating from '@/components/reviews/StarRating';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import { ReviewResponseForm } from '@/components/partner/reviews/ReviewResponseForm';

interface TourSummary {
  tourId: string | number;
  tourTitle: string;
  average: number;
  count: number;
  reviews: PartnerReview[];
}

export default function PartnerReviewsDashboard() {
  const t = useTranslations('partner');
  const params = useParams();
  const locale = (params?.locale as string) || 'en';
  const [expandedTourId, setExpandedTourId] = useState<string | number | null>(null);
  const [respondingReview, setRespondingReview] = useState<PartnerReview | null>(null);

  const {
    data,
    isLoading,
    error,
    refetch,
  } = useQuery({
    queryKey: ['partner-reviews-dashboard'],
    queryFn: async () => fetchPartnerReviews(),
    staleTime: 60_000,
    retry: 1,
  });

  const reviews = useMemo(() => data?.data ?? [], [data]);

  const tourSummaries: TourSummary[] = useMemo(() => {
    const map = new Map<string | number, TourSummary>();
    reviews.forEach((r) => {
      const existing = map.get(r.tour.id);
      if (existing) {
        existing.reviews.push(r);
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
  }, [reviews]);

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

  if (reviews.length === 0) {
    return (
      <EmptyState
        title={t('reviews.noReviews')}
        icon={<MessageSquare className="h-6 w-6" />}
      />
    );
  }

  return (
    <div className="space-y-6">
      {/* Tour summaries */}
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
                    <p className="mt-0.5 text-xs text-gray-500">
                      {t('reviews.reviewsCount', { count: summary.count })}
                    </p>
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
                        <div key={review.id}>
                          <ReviewCard
                            reviewerName={review.traveler_name}
                            rating={review.rating}
                            comment={review.text}
                            edited={false}
                            createdAt={review.submitted_at}
                            locale={locale}
                          />
                          <div className="mt-2">
                            {respondingReview?.id === review.id ? (
                              <ReviewResponseForm
                                review={review}
                                onSuccess={() => {
                                  setRespondingReview(null);
                                  refetch();
                                }}
                              />
                            ) : (
                              <button
                                onClick={() => setRespondingReview(review)}
                                className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                              >
                                {review.response
                                  ? t('reviews.editResponse')
                                  : t('reviews.respond')}
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
    </div>
  );
}
