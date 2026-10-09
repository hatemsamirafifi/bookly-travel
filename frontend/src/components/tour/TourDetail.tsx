import type { TourDetail as TourDetailType } from '@/lib/api/types';
import ImageGallery from './ImageGallery';
import AvailabilityCalendar from './AvailabilityCalendar';
import ReviewList from '@/components/reviews/ReviewList';
import BookingCTA from './BookingCTA';
import StarRating from '@/components/ui/StarRating';
import { useTranslations } from 'next-intl';
import Link from 'next/link';
import TourSectionNav from './TourSectionNav';
import OperatorSummary from './OperatorSummary';
import RelatedTours from './RelatedTours';
import MobileBookingAction from './MobileBookingAction';

interface TourDetailProps {
  tour: TourDetailType;
  locale: string;
}

export default function TourDetail({ tour, locale }: TourDetailProps) {
  const t = useTranslations('tour');
  const hasNonemptyText = (values: (string | null | undefined)[]) =>
    values.some((value) => typeof value === 'string' && value.trim() !== '');
  const showContentFallback =
    locale !== 'en' &&
    tour.content_locale === 'en' &&
    hasNonemptyText([
      tour.title,
      tour.description,
      tour.meeting_point,
      tour.cancellation_policy,
      ...tour.highlights,
      ...tour.inclusions,
      ...tour.exclusions,
      ...(tour.important_information ?? []),
    ]);
  const showItineraryFallback =
    locale !== 'en' && tour.itinerary.length > 0 && tour.itinerary_locale === 'en';
  const languageNames = new Intl.DisplayNames([locale], { type: 'language' });
  const guideLanguages = tour.guide_languages.map((code) => {
    const name = languageNames.of(code) ?? code;
    return name.charAt(0).toLocaleUpperCase(locale) + name.slice(1);
  });
  const sections = [
    { id: 'overview', label: t('about') },
    { id: 'facts', label: t('facts') },
    ...(tour.highlights.length > 0 ? [{ id: 'highlights', label: t('highlights') }] : []),
    ...(tour.itinerary.length > 0 ? [{ id: 'itinerary', label: t('itinerary') }] : []),
    ...(tour.inclusions.length > 0 ? [{ id: 'included', label: t('included') }] : []),
    ...(tour.exclusions.length > 0 ? [{ id: 'excluded', label: t('excluded') }] : []),
    ...(tour.meeting_point ? [{ id: 'meeting', label: t('meetingPoint') }] : []),
    ...(tour.cancellation_policy ? [{ id: 'cancellation', label: t('cancellationPolicy') }] : []),
    ...((tour.important_information?.length ?? 0) > 0 ? [{ id: 'important', label: t('importantInformation') }] : []),
    ...(tour.operator ? [{ id: 'operator', label: t('operator') }] : []),
    ...(tour.reviews.count > 0 ? [{ id: 'reviews', label: t('reviews') }] : []),
    ...((tour.related_tours?.length ?? 0) > 0 ? [{ id: 'related', label: t('relatedTours') }] : []),
  ];

  return (
    <div className="mx-auto max-w-7xl px-4 py-8 pb-24 sm:px-6 lg:px-8 lg:pb-8">
      {/* Header */}
      <div className="mb-6">
        <nav aria-label={t('breadcrumbs')} className="mb-4 flex flex-wrap items-center gap-2 text-sm text-text-muted">
          <Link href={`/${locale}`} className="hover:text-bookly-navy hover:underline">{t('home')}</Link>
          <span aria-hidden="true">/</span>
          <Link href={`/${locale}/categories/${tour.category.slug}`} className="hover:text-bookly-navy hover:underline">{tour.category.name}</Link>
          <span aria-hidden="true">/</span>
          <span aria-current="page" lang={tour.content_locale} className="max-w-[18rem] truncate text-bookly-navy">{tour.title}</span>
        </nav>
        <h1 lang={tour.content_locale} className="text-3xl font-bold text-bookly-navy">{tour.title}</h1>
        <div className="mt-2 flex flex-wrap items-center gap-3 text-sm text-[#5A6B7B]">
          <span className="flex items-center gap-1">
            <svg className="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            {tour.location}
          </span>
          <span aria-hidden="true">|</span>
          <span>{tour.duration.label}</span>
        </div>
        {tour.rating.count > 0 && <div className="mt-2 flex items-center gap-1">
          <StarRating value={tour.rating.average} filledClass="text-[#FFB800]" />
          <span className="ml-1 text-sm text-text-muted">({tour.rating.count} {t('reviews')})</span>
        </div>}
        {guideLanguages.length > 0 && (
          <p className="mt-2 text-sm text-[#0A2540]">
            <span className="font-semibold">{t('liveGuide')}:</span>{' '}
            {guideLanguages.join(', ')}
          </p>
        )}
        {showContentFallback && (
          <p className="mt-2 inline-block rounded bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">
            {t('contentFallback')}
          </p>
        )}
        {showItineraryFallback && (
          <p className="mt-2 inline-block rounded bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">
            {t('itineraryFallback')}
          </p>
        )}
      </div>

      <TourSectionNav label={t('sectionNavigation')} sections={sections} />

      {/* Two-column layout: gallery + sidebar */}
      <div className="mt-8 flex flex-col gap-8 lg:flex-row">
        <div className="lg:w-2/3">
          <ImageGallery images={tour.images} title={tour.title} />
        </div>

        <div className="lg:w-1/3 space-y-5">
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
        </div>
      </div>

      {/* Content sections */}
      <div className="mt-10 space-y-8">
        <div className="max-w-4xl space-y-8">
          {/* Description: heading stays page-localized; text declares its actual language. */}
          <section id="overview" className="scroll-mt-20">
            <h2 className="mb-3 text-xl font-semibold text-[#0A2540]">{t('about')}</h2>
            <div lang={tour.content_locale} className="prose prose-gray max-w-none text-[#0A2540]/80 whitespace-pre-line">
              {tour.description}
            </div>
          </section>

          <section id="facts" className="scroll-mt-20 rounded-xl border border-border bg-surface p-5">
            <h2 className="mb-3 text-xl font-semibold text-bookly-navy">{t('facts')}</h2>
            <dl className="grid gap-4 text-sm sm:grid-cols-2">
              <div><dt className="font-medium text-text-muted">{t('duration')}</dt><dd className="mt-1 text-bookly-navy">{tour.duration.label}</dd></div>
              <div><dt className="font-medium text-text-muted">{t('groupSize')}</dt><dd className="mt-1 text-bookly-navy">{t('groupRange', { min: tour.group_size.min, max: tour.group_size.max })}</dd></div>
              {tour.difficulty_level && <div><dt className="font-medium text-text-muted">{t('difficulty')}</dt><dd className="mt-1 text-bookly-navy">{t(`difficulty_${tour.difficulty_level}`)}</dd></div>}
            </dl>
          </section>

          {/* Highlights */}
          {tour.highlights.length > 0 && (
            <section id="highlights" className="scroll-mt-20">
              <h2 className="mb-3 text-xl font-semibold text-[#0A2540]">{t('highlights')}</h2>
              <ul lang={tour.content_locale} className="space-y-2">
                {tour.highlights.map((h, i) => (
                  <li key={`highlight-${i}`} className="flex items-start gap-2 text-[#0A2540]/80">
                    <svg className="mt-0.5 h-5 w-5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                    </svg>
                    {h}
                  </li>
                ))}
              </ul>
            </section>
          )}

          {/* Inclusions & Exclusions */}
          <div className="grid gap-6 sm:grid-cols-2">
            {tour.inclusions.length > 0 && (
              <section id="included" className="scroll-mt-20">
                <h2 className="mb-3 text-lg font-semibold text-[#0A2540]">{t('included')}</h2>
                <ul lang={tour.content_locale} className="space-y-1">
                  {tour.inclusions.map((item, i) => (
                    <li key={`inclusion-${i}`} className="flex items-start gap-2 text-sm text-[#0A2540]/80">
                      <svg className="mt-0.5 h-4 w-4 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                      </svg>
                      {item}
                    </li>
                  ))}
                </ul>
              </section>
            )}

            {tour.exclusions.length > 0 && (
              <section id="excluded" className="scroll-mt-20">
                <h2 className="mb-3 text-lg font-semibold text-[#0A2540]">{t('excluded')}</h2>
                <ul lang={tour.content_locale} className="space-y-1">
                  {tour.exclusions.map((item, i) => (
                    <li key={`exclusion-${i}`} className="flex items-start gap-2 text-sm text-[#0A2540]/80">
                      <svg className="mt-0.5 h-4 w-4 shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                      </svg>
                      {item}
                    </li>
                  ))}
                </ul>
              </section>
            )}
          </div>

          {/* Meeting Point */}
          {tour.meeting_point && (
            <section id="meeting" className="scroll-mt-20">
              <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">{t('meetingPoint')}</h2>
              <p lang={tour.content_locale} className="flex items-start gap-2 text-[#0A2540]/80">
                <svg className="mt-0.5 h-5 w-5 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                {tour.meeting_point}
              </p>
            </section>
          )}

          {/* Cancellation Policy */}
          {tour.cancellation_policy && (
            <section id="cancellation" className="scroll-mt-20">
              <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">{t('cancellationPolicy')}</h2>
              <p lang={tour.content_locale} className="text-[#0A2540]/80">{tour.cancellation_policy}</p>
            </section>
          )}

          {tour.itinerary.length > 0 && (
            <section id="itinerary" className="scroll-mt-20">
              <h2 className="mb-3 text-xl font-semibold text-bookly-navy">{t('itinerary')}</h2>
              <ol className="space-y-5">
                {tour.itinerary.map((day, dayIndex) => (
                  <li key={`${day.day}-${dayIndex}`} className="rounded-lg border border-border bg-surface p-4">
                    <h3 className="font-semibold text-bookly-navy">{t('day', { number: day.day })}: <span lang={tour.itinerary_locale}>{day.title}</span></h3>
                    {day.description && <p lang={tour.itinerary_locale} className="mt-2 text-text-muted">{day.description}</p>}
                    {day.stops && day.stops.length > 0 && (
                      <ul lang={tour.itinerary_locale} className="mt-3 list-disc space-y-1 pl-5 text-text-muted">
                        {day.stops.map((stop, index) => (
                          <li key={`day-${dayIndex}-stop-${index}`}>
                            {stop.title}{stop.description ? ` — ${stop.description}` : ''}
                          </li>
                        ))}
                      </ul>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          )}
          {(tour.important_information?.length ?? 0) > 0 && (
            <section id="important" className="scroll-mt-20">
              <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">{t('importantInformation')}</h2>
              <ul lang={tour.content_locale} className="list-disc space-y-1 pl-5 text-[#0A2540]/80">
                {tour.important_information?.map((item, index) => <li key={`important-${index}`}>{item}</li>)}
              </ul>
            </section>
          )}
        </div>

        <div className="max-w-4xl space-y-8">
          {tour.operator && (
            <OperatorSummary
              operator={tour.operator}
              title={t('operator')}
              tourCountLabel={t('operatorTourCount', { count: tour.operator.tour_count })}
              reviewCountLabel={t('operatorReviewCount', { count: tour.operator.review_count })}
            />
          )}
          {tour.reviews.count > 0 && <section id="reviews" className="scroll-mt-20"><ReviewList tourSlug={tour.slug} locale={locale} /></section>}
        </div>
      </div>
      {tour.related_tours && <RelatedTours tours={tour.related_tours} locale={locale} title={t('relatedTours')} />}
      <MobileBookingAction availability={tour.availability} pricing={tour.pricing} groupSize={tour.group_size} slug={tour.slug} locale={locale} actionLabel={t('mobileBookingAction')} priceLabel={t('perPerson')} />
    </div>
  );
}
