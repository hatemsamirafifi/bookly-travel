import { Star } from 'lucide-react';

export function RatingBadge({ rating, count, label }: { rating: number | null; count: number; label: string }) {
  if (rating === null || !Number.isFinite(rating) || rating <= 0 || rating > 5 || !Number.isInteger(count) || count <= 0) return null;
  return <span className="inline-flex items-center gap-1 text-sm font-semibold" aria-label={label}><Star className="size-4 fill-current text-trust" aria-hidden="true" /><span aria-hidden="true" className="text-primary">{rating.toFixed(1)}</span><span className="sr-only">{label}</span></span>;
}
