'use client';

import { useState, useEffect, useCallback } from 'react';
import { useTranslations } from 'next-intl';
import { Star } from 'lucide-react';
import { getReviews, respondToReview, updateReviewResponse } from '@/lib/api/partner';
import type { PartnerReview } from '@/types/partner';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import ErrorState from '@/components/ui/ErrorState';

export function ReviewList() {
  const t = useTranslations('partner.reviews');

  const [reviews, setReviews] = useState<PartnerReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [respondingId, setRespondingId] = useState<string | number | null>(null);
  const [responseText, setResponseText] = useState('');
  const [submittingResponse, setSubmittingResponse] = useState(false);

  const fetchReviews = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await getReviews(1);
      setReviews(res.data ?? []);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('loadError'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    fetchReviews();
  }, [fetchReviews]);

  const handleSubmitResponse = async (reviewId: string | number, hasExistingResponse: boolean) => {
    if (!responseText.trim()) return;
    setSubmittingResponse(true);
    try {
      if (hasExistingResponse) {
        await updateReviewResponse(reviewId, responseText.trim());
      } else {
        await respondToReview(reviewId, responseText.trim());
      }
      setReviews((prev) =>
        prev.map((r) =>
          r.id === reviewId
            ? {
                ...r,
                response: {
                  id: r.response?.id ?? reviewId,
                  response_text: responseText.trim(),
                  created_at: r.response?.created_at ?? new Date().toISOString(),
                  updated_at: new Date().toISOString(),
                },
              }
            : r
        )
      );
      setRespondingId(null);
      setResponseText('');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : t('loadError'));
    } finally {
      setSubmittingResponse(false);
    }
  };

  const startResponding = (review: PartnerReview) => {
    setRespondingId(review.id);
    setResponseText(review.response?.response_text ?? '');
  };

  if (loading) {
    return (
      <div className="space-y-4">
        {Array.from({ length: 3 }).map((_, i) => (
          <LoadingSkeleton key={i} variant="card" />
        ))}
      </div>
    );
  }

  if (error) {
    return <ErrorState message={error} onRetry={fetchReviews} />;
  }

  if (reviews.length === 0) {
    return (
      <div className="text-center py-12 text-gray-500">
        {t('noReviews')}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {reviews.map((review) => (
        <div key={review.id} className="bg-white rounded-xl border border-gray-200 p-5">
          <div className="flex items-start justify-between mb-3">
            <div>
              <div className="flex items-center gap-2 mb-1">
                <span className="font-semibold text-[#0A2540]">{review.traveler_name}</span>
                <span className="text-xs text-gray-400">• {t('verifiedTraveler')}</span>
              </div>
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
              {new Date(review.submitted_at).toLocaleDateString()}
            </span>
          </div>

          <p className="text-sm text-gray-700 mb-4">{review.text}</p>

          {review.response ? (
            <div className="bg-gray-50 rounded-lg p-3 border border-gray-100">
              <p className="text-xs font-semibold text-gray-500 mb-1">{t('yourResponse')}</p>
              <p className="text-sm text-gray-700">{review.response.response_text}</p>
              <button
                type="button"
                className="mt-2 text-xs font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
                onClick={() => startResponding(review)}
              >
                {t('editResponse')}
              </button>
            </div>
          ) : null}

          {respondingId === review.id ? (
            <div className="space-y-2 mt-3">
              <textarea
                className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FFB800] focus:border-transparent"
                rows={3}
                placeholder={t('writeResponsePlaceholder')}
                value={responseText}
                onChange={(e) => setResponseText(e.target.value)}
                maxLength={1000}
                disabled={submittingResponse}
              />
              <div className="flex items-center justify-between">
                <span className="text-xs text-gray-400">{t('charCount', { count: responseText.length })}</span>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    className="px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                    onClick={() => {
                      setRespondingId(null);
                      setResponseText('');
                    }}
                    disabled={submittingResponse}
                  >
                    {t('cancel')}
                  </button>
                  <button
                    type="button"
                    className="px-3 py-1.5 text-xs font-medium bg-[#FFB800] hover:bg-[#e6a600] text-[#0A2540] rounded-lg disabled:opacity-50"
                    onClick={() => handleSubmitResponse(review.id, !!review.response)}
                    disabled={!responseText.trim() || submittingResponse}
                  >
                    {submittingResponse
                      ? '...'
                      : review.response
                        ? t('updateResponse')
                        : t('submitResponse')}
                  </button>
                </div>
              </div>
            </div>
          ) : !review.response ? (
            <button
              type="button"
              className="text-sm font-medium text-[#0A2540] hover:text-[#FFB800] transition-colors"
              onClick={() => startResponding(review)}
            >
              {t('respond')}
            </button>
          ) : null}
        </div>
      ))}
    </div>
  );
}
