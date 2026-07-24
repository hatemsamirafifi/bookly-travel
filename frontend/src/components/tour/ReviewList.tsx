import type { ReviewsInfo } from '@/lib/api/types';
import StarRating, { Star } from '@/components/ui/StarRating';

interface ReviewListProps {
  reviews: ReviewsInfo;
}

export default function ReviewList({ reviews }: ReviewListProps) {
  const total = reviews.count;
  const maxBar = Math.max(...Object.values(reviews.distribution), 1);

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      <h3 className="mb-4 text-sm font-semibold text-gray-700">Reviews</h3>

      <div className="flex items-center gap-4 mb-5">
        <div className="text-center">
          <p className="text-3xl font-bold text-gray-900">{reviews.average_rating.toFixed(1)}</p>
          <StarRating value={reviews.average_rating} ariaLabel={`${reviews.average_rating} out of 5 stars`} />
          <p className="mt-1 text-xs text-gray-500">{total} review{total !== 1 ? 's' : ''}</p>
        </div>

        <div className="flex-1 space-y-1">
          {[5, 4, 3, 2, 1].map((star) => {
            const val = reviews.distribution[String(star)] || 0;
            const barWidth = (val / maxBar) * 100;

            return (
              <div key={star} className="flex items-center gap-2 text-xs text-gray-600">
                <span className="w-3 text-right">{star}</span>
                <Star className="h-3 w-3 text-yellow-400 shrink-0" />
                <div className="h-2 flex-1 rounded-full bg-gray-100 overflow-hidden">
                  <div className="h-full rounded-full bg-yellow-400" style={{ width: `${barWidth}%` }} />
                </div>
                <span className="w-6 text-right text-gray-400">{val}</span>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
