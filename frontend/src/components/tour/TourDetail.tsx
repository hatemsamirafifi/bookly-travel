import type { TourDetail as TourDetailType } from '@/lib/api/types';
import ImageGallery from './ImageGallery';
import AvailabilityCalendar from './AvailabilityCalendar';
import ReviewList from '@/components/reviews/ReviewList';
import BookingCTA from './BookingCTA';

interface TourDetailProps {
  tour: TourDetailType;
  locale: string;
}

function StarRating({ rating, count }: { rating: number; count: number }) {
  return (
    <div className="flex items-center gap-1" aria-label={`Rating: ${rating} out of 5`}>
      {[1, 2, 3, 4, 5].map((star) => (
        <svg
          key={star}
          className={`h-4 w-4 ${star <= Math.round(rating) ? 'text-[#FFB800]' : 'text-gray-300'}`}
          fill="currentColor"
          viewBox="0 0 20 20"
          aria-hidden="true"
        >
          <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
        </svg>
      ))}
      <span className="ml-1 text-sm text-[#5A6B7B]">({count} reviews)</span>
    </div>
  );
}

export default function TourDetail({ tour, locale }: TourDetailProps) {
  return (
    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-[#0A2540]">{tour.title}</h1>
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
          <span aria-hidden="true">|</span>
          <span>{tour.category}</span>
        </div>
        <div className="mt-2">
          <StarRating rating={tour.rating.average} count={tour.rating.count} />
        </div>
        {tour.translation_warning === 'partial_translation' && (
          <p className="mt-2 inline-block rounded bg-yellow-50 px-2 py-0.5 text-xs font-medium text-yellow-700">
            Some content is displayed in English
          </p>
        )}
      </div>

      {/* Two-column layout: gallery + sidebar */}
      <div className="flex flex-col gap-8 lg:flex-row">
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
          />
          <AvailabilityCalendar
            availableDates={tour.availability.available_dates}
            nextAvailableDate={tour.availability.next_available_date}
          />
        </div>
      </div>

      {/* Content sections */}
      <div className="mt-10 grid gap-8 lg:grid-cols-3">
        <div className="lg:col-span-2 space-y-8">
          {/* Description */}
          <section>
            <h2 className="mb-3 text-xl font-semibold text-[#0A2540]">About This Tour</h2>
            <div className="prose prose-gray max-w-none text-[#0A2540]/80 whitespace-pre-line">
              {tour.description}
            </div>
          </section>

          {/* Highlights */}
          {tour.highlights.length > 0 && (
            <section>
              <h2 className="mb-3 text-xl font-semibold text-[#0A2540]">Highlights</h2>
              <ul className="space-y-2">
                {tour.highlights.map((h, i) => (
                  <li key={i} className="flex items-start gap-2 text-[#0A2540]/80">
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
              <section>
                <h2 className="mb-3 text-lg font-semibold text-[#0A2540]">What&apos;s Included</h2>
                <ul className="space-y-1">
                  {tour.inclusions.map((item, i) => (
                    <li key={i} className="flex items-start gap-2 text-sm text-[#0A2540]/80">
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
              <section>
                <h2 className="mb-3 text-lg font-semibold text-[#0A2540]">What&apos;s Excluded</h2>
                <ul className="space-y-1">
                  {tour.exclusions.map((item, i) => (
                    <li key={i} className="flex items-start gap-2 text-sm text-[#0A2540]/80">
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
            <section>
              <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">Meeting Point</h2>
              <p className="flex items-start gap-2 text-[#0A2540]/80">
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
            <section>
              <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">Cancellation Policy</h2>
              <p className="text-[#0A2540]/80">{tour.cancellation_policy}</p>
            </section>
          )}
        </div>

        {/* Sidebar: Reviews */}
        <div className="lg:col-span-1">
          <ReviewList tourSlug={tour.slug} locale={locale} />
        </div>
      </div>
    </div>
  );
}
