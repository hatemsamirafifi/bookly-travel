import ImageWithFallback from '@/components/ui/ImageWithFallback';
import Link from 'next/link';
import type { TourCard as TourCardType } from '@/lib/api/types';
import { getImagePlaceholderProps } from '@/lib/images';
import WishlistHeartButton from '@/components/shared/WishlistHeartButton';
import StarRating from '@/components/ui/StarRating';
import { useTranslations } from 'next-intl';

interface TourCardProps {
  tour: TourCardType;
  locale: string;
  imagePriority?: boolean;
}

const BADGE_STYLES: Record<string, string> = {
  featured: 'bg-bookly-navy text-white',
  'best-seller': 'bg-bookly-gold text-bookly-navy font-bold',
  new: 'bg-emerald-600 text-white',
};

const BADGE_KEYS: Record<string, string> = {
  featured: 'badgeFeatured',
  'best-seller': 'badgeBestSeller',
  new: 'badgeNew',
};

export default function TourCard({ tour, locale, imagePriority = false }: TourCardProps) {
  const t = useTranslations('search');
  const nextDate = tour.next_available_date
    ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeZone: 'UTC' }).format(
        new Date(`${tour.next_available_date}T00:00:00Z`)
      )
    : null;

  return (
    <div className="group relative flex flex-col overflow-hidden rounded-xl border border-border bg-surface shadow-xs transition duration-200 hover:shadow-md">
      <Link
        href={`/${locale}/tours/${tour.slug}`}
        className="flex flex-1 flex-col rounded-xl focus-visible:outline-2 focus-visible:outline-focus focus-visible:outline-offset-2"
      >
        {/* Cover image container standardized on 4:3 ratio */}
        <div className="relative aspect-[4/3] w-full overflow-hidden bg-surface-alt">
          {tour.cover_image_url ? (
            <ImageWithFallback
              src={tour.cover_image_url}
              alt={tour.title}
              fill
              loading={imagePriority ? 'eager' : 'lazy'}
              fetchPriority={imagePriority ? 'high' : undefined}
              sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
              className="object-cover transition-transform duration-300 group-hover:scale-105"
              {...getImagePlaceholderProps()}
            />
          ) : (
            <div className="flex h-full items-center justify-center text-text-muted">
              <svg className="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={1.5}
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
          )}

          {/* Badges on top-left */}
          <div className="absolute left-3 top-3 flex flex-col gap-1.5">
            {tour.badge && BADGE_KEYS[tour.badge] && (
              <span
                className={`rounded-md px-2 py-0.5 text-xs font-semibold shadow-xs ${
                  BADGE_STYLES[tour.badge] || 'bg-bookly-navy text-white'
                }`}
              >
                {t(BADGE_KEYS[tour.badge])}
              </span>
            )}
            {nextDate && (
              <span className="rounded-md bg-surface/90 px-2 py-0.5 text-xs font-medium text-bookly-navy shadow-xs backdrop-blur-xs">
                {t('nextAvailable', { date: nextDate })}
              </span>
            )}
          </div>
        </div>

        {/* Content area */}
        <div className="flex flex-1 flex-col p-4">
          {/* Contextual metadata */}
          <div className="mb-1.5 flex items-center gap-1.5 text-xs text-text-muted">
            <span className="truncate">{tour.location}</span>
            <span aria-hidden="true">·</span>
            <span className="shrink-0">{tour.duration_label}</span>
          </div>

          {/* Clamped 2-line title */}
          <h2 className="mb-2 line-clamp-2 text-base font-bold text-bookly-navy group-hover:text-primary-dark">
            {tour.title}
          </h2>

          {/* Star rating & review count */}
          {tour.rating && tour.rating.count > 0 ? (
            <div className="mb-3 flex items-center gap-1.5">
              <StarRating value={tour.rating.average} />
              <span className="text-xs font-semibold text-bookly-navy">
                {tour.rating.average.toFixed(1)}
              </span>
              <span className="text-xs text-text-muted">
                (
                {t(tour.rating.count === 1 ? 'reviewCount_one' : 'reviewCount', {
                  count: tour.rating.count,
                })}
                )
              </span>
            </div>
          ) : (
            <div className="mb-3">
              <span className="text-xs text-text-muted">{t('newRating')}</span>
            </div>
          )}

          {/* Footer with category and price */}
          <div className="mt-auto flex items-center justify-between border-t border-border pt-3">
            <span className="rounded-full bg-surface-alt px-2.5 py-0.5 text-xs font-medium text-bookly-navy">
              {tour.category}
            </span>
            <div className="text-right">
              <span className="text-xs text-text-muted mr-1">{t('from')}</span>
              <span className="text-base font-bold text-bookly-navy">{tour.price.formatted}</span>
            </div>
          </div>
        </div>
      </Link>

      {/* Wishlist Heart Action (independent clickable target) */}
      <div className="absolute right-3 top-3 z-10">
        <WishlistHeartButton tourId={tour.id} tourTitle={tour.title} compact />
      </div>
    </div>
  );
}
