import Link from 'next/link';
import { useTranslations } from 'next-intl';
import ReviewForm from './ReviewForm';
import type { TravelerReview } from '@/types/traveler';

interface MyReviewCardProps {
  review: TravelerReview;
  locale: string;
  editing: boolean;
  onEdit: () => void;
  onCancelEdit: () => void;
  onUpdate: (data: { rating: number; comment?: string }) => Promise<void>;
}

export default function MyReviewCard({
  review,
  locale,
  editing,
  onEdit,
  onCancelEdit,
  onUpdate,
}: MyReviewCardProps) {
  const t = useTranslations('traveler.myReviews');

  if (editing) {
    return (
      <ReviewForm
        locale={locale}
        existingReview={{
          id: Number(review.id),
          rating: review.rating,
          comment: review.text,
        }}
        onSubmit={onUpdate}
        onCancel={onCancelEdit}
      />
    );
  }

  return (
    <article className="rounded-lg border border-gray-200 bg-white p-5">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <Link href={`/${locale}/tours/${review.tour.slug}`} className="font-semibold text-[#0A2540] hover:underline">
          {review.tour.name}
        </Link>
        <span className="text-sm font-medium text-amber-700">{t('rating', { rating: review.rating })}</span>
      </div>
      <p className="mt-3 text-sm text-gray-700">{review.text}</p>
      <div className="mt-3 flex items-center justify-between">
        <p className="text-xs text-gray-500">{new Date(review.submitted_at).toLocaleDateString()}</p>
        {review.can_edit && (
          <button
            type="button"
            onClick={onEdit}
            className="text-xs font-medium text-blue-600 hover:text-blue-800 underline"
          >
            {t('edit')}
          </button>
        )}
      </div>
    </article>
  );
}
