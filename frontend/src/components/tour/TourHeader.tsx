import { Breadcrumbs } from '@/components/ui/Breadcrumbs';
import { RatingBadge } from '@/components/ui/RatingBadge';
import WishlistButton from '@/components/wishlist/WishlistButton';
import { hasGenuineReviewCount, isGenuineReviewScore } from '@/lib/tours/detail-presentation';
import { useTranslations } from 'next-intl';

interface TourHeaderProps {
  locale: string;
  title: string;
  contentLocale: string;
  location: string;
  categorySlug: string;
  categoryName: string;
  /** Public tour id for the single current-tour wishlist control. */
  tourId: string | number;
  /** Authoritative genuine eligible summary (tour.reviews); the listing card
   *  rating is never used here so a stale card value cannot surface a score. */
  reviewAverage: number;
  reviewCount: number;
}

/** Header: breadcrumbs, title, location, the single current-tour wishlist
 *  control and the genuine rating. The scored badge renders only for a valid
 *  score; a positive count with an unavailable average keeps the review link
 *  for real browsing without fabricated stars. */
export default function TourHeader({
  locale,
  title,
  contentLocale,
  location,
  categorySlug,
  categoryName,
  tourId,
  reviewAverage,
  reviewCount,
}: TourHeaderProps) {
  const t = useTranslations('tour');

  return (
    <div className="mb-6">
      <Breadcrumbs
        label={t('breadcrumbs')}
        items={[
          { label: t('home'), href: `/${locale}` },
          { label: categoryName, href: `/${locale}/categories/${categorySlug}` },
          { label: title, lang: contentLocale },
        ]}
      />
      <div className="mt-4 flex items-start justify-between gap-4">
        <h1 lang={contentLocale} className="text-page-title font-bold text-primary">{title}</h1>
        <WishlistButton tourId={tourId} locale={locale} />
      </div>
      <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-text-muted">
        <span className="flex items-center gap-1">
          <svg className="h-4 w-4 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          {location}
        </span>
      </div>
      {hasGenuineReviewCount(reviewCount) && (
        <div className="mt-2 flex flex-wrap items-center gap-2">
          {isGenuineReviewScore(reviewCount, reviewAverage) && (
            <RatingBadge
              rating={reviewAverage}
              count={reviewCount}
              label={t('ratingLabel', { rating: reviewAverage })}
            />
          )}
          <a href="#reviews" className="text-sm text-text-muted hover:text-primary hover:underline">
            ({reviewCount} {t('reviews')})
          </a>
        </div>
      )}
    </div>
  );
}
