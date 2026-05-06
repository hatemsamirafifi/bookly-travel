import type { ReviewsInfo } from '@/lib/api/types';

interface ReviewListProps {
  reviews: ReviewsInfo;
}

function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
      {[1, 2, 3, 4, 5].map((star) => (
        <svg
          key={star}
          className={`h-4 w-4 ${star <= Math.round(rating) ? 'text-yellow-400' : 'text-gray-300'}`}
          fill="currentColor"
          viewBox="0 0 20 20"
          aria-hidden="true"
        >
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
    </div>
  );
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
          <StarRating rating={reviews.average_rating} />
          <p className="mt-1 text-xs text-gray-500">{total} review{total !== 1 ? 's' : ''}</p>
        </div>

        <div className="flex-1 space-y-1">
          {[5, 4, 3, 2, 1].map((star) => {
            const val = reviews.distribution[String(star)] || 0;
            const pct = total > 0 ? (val / total) * 100 : 0;
            const barWidth = (val / maxBar) * 100;

            return (
              <div key={star} className="flex items-center gap-2 text-xs text-gray-600">
                <span className="w-3 text-right">{star}</span>
                <svg className="h-3 w-3 text-yellow-400 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                </svg>
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
