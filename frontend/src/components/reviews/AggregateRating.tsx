import { useTranslations } from 'next-intl';
import StarRating from './StarRating';

interface AggregateRatingProps {
  averageRating: number | null;
  reviewCount: number;
}

export default function AggregateRating({ averageRating, reviewCount }: AggregateRatingProps) {
  const t = useTranslations('reviews');
  const hasReviews = reviewCount > 0 && averageRating !== null;

  return (
    <div className="flex items-center gap-3">
      {hasReviews ? (
        <>
          <span className="text-2xl font-bold text-[#0A2540]" aria-label={`Average rating ${averageRating?.toFixed(1)} out of 5`}>
            {averageRating?.toFixed(1)}
          </span>
          <div>
            <StarRating value={Math.round(averageRating!)} readOnly size="sm" />
            <p className="text-xs text-gray-500 mt-0.5">
              {t('review_count', { count: reviewCount })}
            </p>
          </div>
        </>
      ) : (
        <div>
          <StarRating value={0} readOnly size="sm" />
          <p className="text-xs text-gray-400 mt-0.5">{t('no_reviews_yet')}</p>
        </div>
      )}
    </div>
  );
}
