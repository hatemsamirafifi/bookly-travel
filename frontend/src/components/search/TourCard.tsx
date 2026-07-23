'use client';

import Image from 'next/image';
import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import WishlistButton from '@/components/wishlist/WishlistButton';
import StarRating from '@/components/ui/StarRating';

interface TourCardProps {
  tour: TourCardType;
  locale: string;
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
            {...getImagePlaceholderProps()}
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
        <div className="absolute right-3 top-3" onClick={(event) => event.preventDefault()}>
          <WishlistButton tourId={tour.id} locale={locale} compact />
        </div>
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
          <div className="flex items-center gap-0.5">
            <StarRating value={tour.rating.average} />
            <span className="ml-1 text-sm text-gray-600">({tour.rating.average})</span>
          </div>
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
