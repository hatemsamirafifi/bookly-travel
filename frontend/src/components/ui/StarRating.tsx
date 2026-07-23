interface StarRatingProps {
  /** Numeric rating 0–5; stars are filled by Math.round(value). */
  value: number;
  /** Tailwind size class for each star. */
  size?: string;
  /** Tailwind color class applied to filled stars. */
  filledClass?: string;
  /** Tailwind color class applied to empty stars. */
  emptyClass?: string;
  /** Wrapper element class. */
  className?: string;
  /** Overrides the default `Rating: {value} out of 5` aria-label. */
  ariaLabel?: string;
}

/**
 * The single source of the star SVG path (spec 006 reuse cleanup). Use this
 * for decorative single-star bullets; use <StarRating> for a 5-star rating.
 */
export function Star({ className = 'h-4 w-4 text-yellow-400' }: { className?: string }) {
  return (
    <svg className={className} fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
      <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
    </svg>
  );
}

/**
 * Read-only 5-star rating display (spec 006 reuse cleanup). Replaces the
 * duplicated star SVG previously inlined in TourCard, TourDetail, and
 * ReviewList. The interactive form variant lives at
 * `components/reviews/StarRating.tsx`.
 */
export default function StarRating({
  value,
  size = 'h-4 w-4',
  filledClass = 'text-yellow-400',
  emptyClass = 'text-gray-300',
  className = 'flex items-center gap-0.5',
  ariaLabel,
}: StarRatingProps) {
  const rounded = Math.round(value);

  return (
    <div
      className={className}
      role="img"
      aria-label={ariaLabel ?? `Rating: ${value} out of 5`}
    >
      {[1, 2, 3, 4, 5].map((star) => (
        <Star
          key={star}
          className={`${size} ${star <= rounded ? filledClass : emptyClass}`}
        />
      ))}
    </div>
  );
}