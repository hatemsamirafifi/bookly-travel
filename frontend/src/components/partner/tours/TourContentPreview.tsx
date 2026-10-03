import { useTranslations } from 'next-intl';
import ImageGallery from '@/components/tour/ImageGallery';
import type { TourItineraryDay } from '@/lib/api/types';
import type { TourMedia } from '@/types/tour';

interface TourContentPreviewProps {
  title: string;
  description: string;
  itinerary: TourItineraryDay[];
  media: TourMedia[];
  /** Authored English source extras (Spec 019); shown when provided. */
  highlights?: string[];
  inclusions?: string[];
  exclusions?: string[];
  important_information?: string[] | null;
  meeting_point?: string | null;
  cancellation_policy?: string | null;
}

export function TourContentPreview({
  title,
  description,
  itinerary,
  media,
  highlights = [],
  inclusions = [],
  exclusions = [],
  important_information = [],
  meeting_point = null,
  cancellation_policy = null,
}: TourContentPreviewProps) {
  const t = useTranslations('partner.tours.form');
  const images = [...media]
    .sort((a, b) => Number(b.is_cover) - Number(a.is_cover) || a.sort_order - b.sort_order)
    .map((item) => ({ url: item.url, is_cover: item.is_cover, alt: item.alt_text || title }));

  return (
    <section className="space-y-5 rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6" aria-label={t('previewTour')}>
      <h2 className="text-2xl font-bold text-bookly-navy">{title || '—'}</h2>
      {images.length > 0 && <ImageGallery images={images} title={title} />}
      {description && <p className="whitespace-pre-line text-text-muted">{description}</p>}
      {highlights.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('highlights')}</h3>
          <ul className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {highlights.map((item) => <li key={item}>{item}</li>)}
          </ul>
        </div>
      )}
      {itinerary.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('itinerary')}</h3>
          {itinerary.map((day, index) => (
            <div key={index} className="border-l-2 border-bookly-gold pl-4">
              <h4 className="font-semibold text-bookly-navy">{t('dayNumber', { number: index + 1 })}: {day.title}</h4>
              {day.description && <p className="mt-1 text-sm text-text-muted">{day.description}</p>}
              {(day.stops ?? []).length > 0 && (
                <ol className="mt-2 list-inside list-decimal space-y-1 text-sm text-bookly-navy">
                  {day.stops?.map((stop, stopIndex) => <li key={stopIndex}>{stop.title}</li>)}
                </ol>
              )}
            </div>
          ))}
        </div>
      )}
      {inclusions.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('inclusions')}</h3>
          <ul className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {inclusions.map((item) => <li key={item}>{item}</li>)}
          </ul>
        </div>
      )}
      {exclusions.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('exclusions')}</h3>
          <ul className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {exclusions.map((item) => <li key={item}>{item}</li>)}
          </ul>
        </div>
      )}
      {(important_information ?? []).length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('importantInformation')}</h3>
          <ul className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {(important_information ?? []).map((item) => <li key={item}>{item}</li>)}
          </ul>
        </div>
      )}
      {meeting_point && (
        <p className="text-sm text-text-muted">{t('meetingPoint')}: {meeting_point}</p>
      )}
      {cancellation_policy && (
        <p className="text-sm text-text-muted">{t('cancellationPolicy')}: {cancellation_policy}</p>
      )}
    </section>
  );
}
