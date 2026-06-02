import Image from 'next/image';
import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';

interface TourCardProps {
  tour: TourCardType;
  locale: string;
}

function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5" aria-label={`Rating: ${rating} out of 5`}>
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
      <span className="ml-1 text-sm text-gray-600">({rating})</span>
    </div>
  );
}

export default function TourCard({ tour, locale }: TourCardProps) {
  return (
    <Link
      href={`/${locale}/tours/${tour.slug}`}
      className="group block overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-[#0A2540]"
    >
      <div className="relative aspect-[16/10] overflow-hidden bg-gray-100">
        {tour.cover_image_url ? (
          <Image
            src={tour.cover_image_url}
            alt={tour.title}
            fill
            sizes="(min-width: 768px) 33vw, (min-width: 640px) 50vw, 100vw"
            className="object-cover transition-transform duration-300 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-400">
            <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
        )}
        {tour.next_available_date && (
          <span className="absolute left-3 top-3 rounded-md bg-white/90 px-2 py-1 text-xs font-medium text-gray-800 shadow backdrop-blur-sm">
            Next: {new Date(tour.next_available_date).toLocaleDateString()}
          </span>
        )}
      </div>

      <div className="p-4">
        <div className="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>{tour.location}</span>
          <span aria-hidden="true">·</span>
          <span>{tour.duration_label}</span>
        </div>

        <h3 className="mb-2 text-lg font-semibold text-[#0A2540] group-hover:text-[#071b2e] line-clamp-2">
          {tour.title}
        </h3>

        <div className="mb-3">
          <StarRating rating={tour.rating.average} />
          <span className="text-xs text-gray-500">({tour.rating.count} reviews)</span>
        </div>

        <div className="flex items-center justify-between border-t border-gray-100 pt-3">
          <span className="text-xs rounded-full bg-[#F7F9FB] px-2.5 py-0.5 font-medium text-[#0A2540]">
            {tour.category}
          </span>
          <span className="text-lg font-bold text-[#0A2540]">{tour.price.formatted}</span>
        </div>
      </div>
    </Link>
  );
}
