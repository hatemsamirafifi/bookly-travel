import StarRating from './StarRating';

interface ReviewCardProps {
  reviewerName: string;
  rating: number;
  comment?: string | null;
  edited: boolean;
  createdAt: string;
  locale?: string;
}

export default function ReviewCard({
  reviewerName,
  rating,
  comment,
  edited,
  createdAt,
  locale,
}: ReviewCardProps) {
  const date = new Date(createdAt);
  const formattedDate = new Intl.DateTimeFormat(locale || 'en', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date);

  return (
    <div className="border-b border-gray-100 py-3 last:border-b-0">
      <div className="flex items-center gap-2 mb-1">
        <span className="text-sm font-medium text-gray-800">{reviewerName}</span>
        {edited && (
          <span className="text-xs bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full font-medium">
            Edited
          </span>
        )}
      </div>

      <div className="flex items-center gap-2 mb-1">
        <StarRating value={rating} readOnly size="sm" />
        <span className="text-xs text-gray-400">{formattedDate}</span>
      </div>

      {comment && (
        <p className="text-sm text-gray-600 leading-relaxed mt-1">{comment}</p>
      )}
    </div>
  );
}
