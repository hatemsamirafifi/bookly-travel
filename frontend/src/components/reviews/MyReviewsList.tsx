'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useTranslations } from 'next-intl';
import { getTravelerReviews } from '@/lib/api/traveler';
import type { TravelerReview } from '@/types/traveler';

export default function MyReviewsList({ locale }: { locale: string }) {
  const t = useTranslations('traveler.myReviews');
  const [reviews, setReviews] = useState<TravelerReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    getTravelerReviews()
      .then((res) => setReviews(res.data))
      .catch(() => setError(t('loadError')))
      .finally(() => setLoading(false));
  }, [t]);

  if (loading) {
    return <div className="space-y-3">{[1, 2].map((i) => <div key={i} className="h-32 animate-pulse rounded-lg bg-gray-100" />)}</div>;
  }

  if (error) {
    return <p className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</p>;
  }

  if (reviews.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-gray-300 bg-white py-12 text-center">
        <p className="text-gray-600">{t('empty')}</p>
        <Link href={`/${locale}/search`} className="mt-4 inline-flex rounded-xl bg-[#FFB800] px-5 py-2.5 text-sm font-semibold text-[#0A2540]">
          {t('browseTours')}
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {reviews.map((review) => (
        <article key={review.id} className="rounded-lg border border-gray-200 bg-white p-5">
          <div className="flex flex-wrap items-start justify-between gap-2">
            <Link href={`/${locale}/tours/${review.tour.slug}`} className="font-semibold text-[#0A2540] hover:underline">
              {review.tour.name}
            </Link>
            <span className="text-sm font-medium text-amber-700">{t('rating', { rating: review.rating })}</span>
          </div>
          <p className="mt-3 text-sm text-gray-700">{review.text}</p>
          <p className="mt-3 text-xs text-gray-500">{new Date(review.submitted_at).toLocaleDateString()}</p>
        </article>
      ))}
    </div>
  );
}
