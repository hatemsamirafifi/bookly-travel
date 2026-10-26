import ReviewList from '@/components/reviews/ReviewList';

interface TourReviewsProps {
  tourSlug: string;
  locale: string;
  /** Localized section title from the compositor catalog. */
  title: string;
}

/** Narrow server-compatible reviews wrapper: the localized section shell
 *  with a real heading; rows, paging and authorization stay in ReviewList. */
export default function TourReviews({ tourSlug, locale, title }: TourReviewsProps) {
  return (
    <section id="reviews" className="scroll-mt-20" aria-labelledby="reviews-title">
      <h2 id="reviews-title" className="mb-3 text-xl font-semibold text-bookly-navy">{title}</h2>
      <ReviewList tourSlug={tourSlug} locale={locale} />
    </section>
  );
}
