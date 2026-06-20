'use client';

import { useState } from 'react';
import { useTranslations } from 'next-intl';
import { useMyReviews } from '@/hooks/useMyReviews';
import MyReviewCard from './MyReviewCard';
import EmptyState from '@/components/ui/EmptyState';
import ErrorState from '@/components/ui/ErrorState';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';
import { editReview } from '@/lib/reviews/review-api';

interface MyReviewsListProps {
  locale: string;
}

export default function MyReviewsList({ locale }: MyReviewsListProps) {
  const t = useTranslations('traveler.myReviews');
  const { data: reviews, isLoading, error, refetch } = useMyReviews();
  const [editingId, setEditingId] = useState<string | number | null>(null);

  if (isLoading) {
    return <LoadingSkeleton variant="list" count={2} />;
  }

  if (error) {
    return <ErrorState message={t('loadError')} onRetry={() => refetch()} />;
  }

  if (!reviews || reviews.length === 0) {
    return (
      <EmptyState
        title={t('empty')}
        cta={{ label: t('browseTours'), href: `/${locale}/search` }}
      />
    );
  }

  return (
    <div className="space-y-3">
      {reviews.map((review) => (
        <MyReviewCard
          key={review.id}
          review={review}
          locale={locale}
          editing={editingId === review.id}
          onEdit={() => setEditingId(review.id)}
          onCancelEdit={() => setEditingId(null)}
          onUpdate={async (data) => {
            await editReview(Number(review.id), {
              rating: data.rating,
              comment: data.comment || undefined,
            });
            setEditingId(null);
            refetch();
          }}
        />
      ))}
    </div>
  );
}
