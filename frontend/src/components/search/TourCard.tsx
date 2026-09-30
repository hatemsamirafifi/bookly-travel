'use client';

import Image from 'next/image';
import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import WishlistButton from '@/components/wishlist/WishlistButton';
import StarRating from '@/components/ui/StarRating';
import { useTranslations } from 'next-intl';

interface TourCardProps {
  tour: TourCardType;
  locale: string;
  imagePriority?: boolean;
}

export default function TourCard({ tour, locale, imagePriority = false }: TourCardProps) {
  const t = useTranslations('search');
  const nextDate = tour.next_available_date
    ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${tour.next_available_date}T00:00:00Z`))
    : null;

  return (
    <div className="group relative overflow-hidden rounded-xl border border-border bg-surface shadow-card transition-shadow hover:shadow-lg">
      <Link
        href={`/${locale}/tours/${tour.slug}`}
        className="block focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus"
      >
        <div className="relative aspect-[16/10] overflow-hidden bg-surface-alt">
        {tour.cover_image_url ? (
          <Image
            src={tour.cover_image_url}
            alt={tour.title}
            fill
            loading={imagePriority ? 'eager' : 'lazy'}
            fetchPriority={imagePriority ? 'high' : undefined}
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
        {nextDate && (
          <span className="absolute left-3 top-3 rounded-md bg-surface/90 px-2 py-1 text-xs font-medium text-bookly-navy shadow backdrop-blur-sm">
            {t('nextAvailable', { date: nextDate })}
          </span>
        )}
      </div>

      <div className="p-4">
        <div className="mb-1 flex items-center gap-2 text-xs text-text-muted">
          <span>{tour.location}</span>
          <span aria-hidden="true">·</span>
          <span>{tour.duration_label}</span>
        </div>

        <h2 className="mb-2 text-lg font-semibold text-bookly-navy group-hover:text-primary-dark line-clamp-2">
          {tour.title}
        </h2>

        {tour.rating.count > 0 && (
          <div className="mb-3">
            <div className="flex items-center gap-0.5">
              <StarRating value={tour.rating.average} />
              <span className="ml-1 text-sm text-text-muted">({tour.rating.average})</span>
            </div>
            <span className="text-xs text-text-muted">({t(tour.rating.count === 1 ? 'reviewCount_one' : 'reviewCount', { count: tour.rating.count })})</span>
          </div>
        )}

        <div className="flex items-center justify-between border-t border-border pt-3">
          <span className="text-xs rounded-full bg-surface-alt px-2.5 py-0.5 font-medium text-bookly-navy">
            {tour.category}
          </span>
          <span className="text-lg font-bold text-bookly-navy">{tour.price.formatted}</span>
        </div>
      </div>
      </Link>
      <div className="absolute right-3 top-3 z-10">
        <WishlistButton tourId={tour.id} locale={locale} compact />
      </div>
    </div>
  );
}
