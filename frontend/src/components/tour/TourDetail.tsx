import type { TourDetail as TourDetailType } from '@/lib/api/types';
import {
  getDetailPresentation,
  type DetailSectionId,
} from '@/lib/tours/detail-presentation';
import ImageGallery from './ImageGallery';
import AvailabilityCalendar from './AvailabilityCalendar';
import ReviewList from '@/components/reviews/ReviewList';
import BookingCTA from './BookingCTA';
import { useTranslations } from 'next-intl';
import TourSectionNav from './TourSectionNav';
import OperatorSummary from './OperatorSummary';
import RelatedTours from './RelatedTours';
import MobileBookingAction from './MobileBookingAction';
import TourHeader from './TourHeader';
import TourFacts from './TourFacts';
import TourHighlights from './TourHighlights';
import TourOverview from './TourOverview';
import TourItinerary from './TourItinerary';
import TourInclusions from './TourInclusions';
import { TourCancellation, TourImportantInformation, TourMeetingPoint } from './TourMeetingPoint';
import { Container } from '@/components/ui/Container';

interface TourDetailProps {
  tour: TourDetailType;
  locale: string;
}

/** Detail compositor: presence derived once from the model drives sections and
 *  nav. Gallery/booking/operator/reviews/related behavior is preserved. */
export default function TourDetail({ tour, locale }: TourDetailProps) {
  const t = useTranslations('tour');
  const presentation = getDetailPresentation(tour, locale);
  const present = new Set<DetailSectionId>(presentation.presentSectionIds);

  const sectionLabels: Record<DetailSectionId, string> = {
    overview: t('about'),
    facts: t('facts'),
    highlights: t('highlights'),
    itinerary: t('itinerary'),
    included: t('included'),
    excluded: t('excluded'),
    meeting: t('meetingPoint'),
    cancellation: t('cancellationPolicy'),
    important: t('importantInformation'),
    operator: t('operator'),
    reviews: t('reviews'),
    related: t('relatedTours'),
  };
  const sections = presentation.presentSectionIds.map((id) => ({ id, label: sectionLabels[id] }));

  return (
    <Container className="py-8 pb-24 lg:pb-8">
      <TourHeader
        locale={locale}
        title={tour.title}
        contentLocale={tour.content_locale}
        location={tour.location}
        categorySlug={tour.category.slug}
        categoryName={tour.category.name}
        ratingAverage={tour.rating.average}
        ratingCount={tour.rating.count}
        reviewCount={tour.reviews.count}
      />
      {presentation.disclosure.showContentFallback && (
        <p className="mb-2 inline-block rounded border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
          {t('contentFallback')}
        </p>
      )}
      {presentation.disclosure.showItineraryFallback && (
        <p className="mb-2 ml-2 inline-block rounded border border-warning/30 bg-warning/10 px-2 py-0.5 text-xs font-medium text-warning">
          {t('itineraryFallback')}
        </p>
      )}

      <ImageGallery images={tour.images} title={tour.title} contentLocale={tour.content_locale} />

      <TourSectionNav label={t('sectionNavigation')} sections={sections} />

      {/* Single column below 1024px; article/sidebar ~2:1 at larger widths. */}
      <div className="mt-8 grid gap-8 lg:grid-cols-3">
        <article className="space-y-8 lg:col-span-2">
          <TourOverview text={tour.description} contentLocale={tour.content_locale} />

          <TourFacts
            locale={locale}
            durationValue={tour.duration.label}
            groupMin={tour.group_size.min}
            groupMax={tour.group_size.max}
            difficulty={tour.difficulty_level}
            guideLanguages={tour.guide_languages}
          />

          {present.has('highlights') && (
            <TourHighlights items={tour.highlights} contentLocale={tour.content_locale} />
          )}

          {present.has('itinerary') && (
            <TourItinerary days={tour.itinerary} itineraryLocale={tour.itinerary_locale} />
          )}

          <TourInclusions
            inclusions={tour.inclusions}
            exclusions={tour.exclusions}
            contentLocale={tour.content_locale}
          />

          <TourMeetingPoint meetingPoint={tour.meeting_point} contentLocale={tour.content_locale} />
          <TourCancellation cancellationPolicy={tour.cancellation_policy} contentLocale={tour.content_locale} />
          <TourImportantInformation items={tour.important_information ?? []} contentLocale={tour.content_locale} />

          {present.has('operator') && tour.operator && (
            <OperatorSummary
              operator={tour.operator}
              title={t('operator')}
              tourCountLabel={t('operatorTourCount', { count: tour.operator.tour_count })}
              reviewCountLabel={t('operatorReviewCount', { count: tour.operator.review_count })}
              verifiedLabel={t('operatorVerified')}
            />
          )}
          {present.has('reviews') && <section id="reviews" className="scroll-mt-20"><ReviewList tourSlug={tour.slug} locale={locale} /></section>}
        </article>

        <aside className="space-y-5 lg:col-span-1">
          <BookingCTA
            pricing={tour.pricing}
            availability={tour.availability}
            groupSize={tour.group_size}
            locale={locale}
            slug={tour.slug}
            tourId={tour.id}
          />
          <AvailabilityCalendar
            availableDates={tour.availability.available_dates}
            nextAvailableDate={tour.availability.next_available_date}
            locale={locale}
          />
        </aside>
      </div>
      {present.has('related') && tour.related_tours && <RelatedTours tours={tour.related_tours} locale={locale} title={t('relatedTours')} />}
      <MobileBookingAction availability={tour.availability} pricing={tour.pricing} groupSize={tour.group_size} slug={tour.slug} locale={locale} actionLabel={t('mobileBookingAction')} priceLabel={t('perPerson')} />
    </Container>
  );
}
