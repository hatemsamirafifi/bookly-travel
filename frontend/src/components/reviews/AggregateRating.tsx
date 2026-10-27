import { useTranslations } from 'next-intl';
import StarRating from '@/components/ui/StarRating';
import { hasGenuineReviewCount, isGenuineReviewScore } from '@/lib/tours/detail-presentation';

interface AggregateRatingProps {
  averageRating: number | null;
  reviewCount: number;
}

export default function AggregateRating({ averageRating, reviewCount }: AggregateRatingProps) {
  const t = useTranslations('reviews');
  const tt = useTranslations('tour');
  if (!hasGenuineReviewCount(reviewCount)) {
    // An actual zero may use the localized no-review copy; any other
    // invalid count is not a known zero and invents nothing.
    if (reviewCount === 0) {
      return (
        <div>
          <p className="text-xs text-gray-500 mt-0.5">{t('no_reviews_yet')}</p>
        </div>
      );
    }
    return null;
  }
  if (!isGenuineReviewScore(reviewCount, averageRating)) {
    // Genuine count without a score: browsing stays possible, but the count
    // is never relabeled as zero and no stars are fabricated.
    return (
      <div>
        <p className="text-xs text-gray-500 mt-0.5">
          {t('review_count', { count: reviewCount })}
        </p>
      </div>
    );
  }
  return (
    <div className="flex items-center gap-3">
      <span className="text-2xl font-bold text-[#0A2540]">
        {averageRating.toFixed(1)}
      </span>
      <div>
        <StarRating value={averageRating} ariaLabel={tt('ratingLabel', { rating: averageRating.toFixed(1) })} />
        <p className="text-xs text-gray-500 mt-0.5">
          {t('review_count', { count: reviewCount })}
        </p>
      </div>
    </div>
  );
}
