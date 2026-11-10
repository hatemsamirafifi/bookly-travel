import { sanitizeHtml } from '@/lib/sanitizeHtml';
import { useTranslations } from 'next-intl';
import ImageGallery from '@/components/tour/ImageGallery';
import type { TourItineraryDay, OwnedTranslationStatus, ContentSourceLocale } from '@/lib/api/types';
import type { TourMedia } from '@/types/tour';

interface TourContentPreviewProps {
  title: string;
  description: string;
  itinerary: TourItineraryDay[];
  media: TourMedia[];
  /** Authored source extras; shown when provided. */
  highlights?: string[];
  inclusions?: string[];
  exclusions?: string[];
  important_information?: string[] | null;
  meeting_point?: string | null;
  cancellation_policy?: string | null;
  /** Original writing language of the content; lang tagging follows it
   *  (the preview shows exactly what will be authored, in its language). */
  sourceLocale?: ContentSourceLocale;
  /** Sanitized owned readiness of the last saved revision, keyed by the
   *  generated (non-source) language it describes. Absent for unsaved
   *  drafts. */
  translationStatuses?: { en?: OwnedTranslationStatus; es?: OwnedTranslationStatus; it?: OwnedTranslationStatus } | null;
}

/** Generated languages the saved-readiness block can describe. */
const GENERATED_LOCALES = ['en', 'es', 'it'] as const;

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
  sourceLocale = 'en',
  translationStatuses = null,
}: TourContentPreviewProps) {
  const t = useTranslations('partner.tours.form');
  const images = [...media]
    .sort((a, b) => Number(b.is_cover) - Number(a.is_cover) || a.sort_order - b.sort_order)
    .map((item) => ({ url: item.url, is_cover: item.is_cover, alt: item.alt_text || title }));
  // Readiness rows describe the generated translations of THIS tour's
  // source — never the authored language itself.
  const readinessRows = translationStatuses
    ? GENERATED_LOCALES.filter((locale) => locale !== sourceLocale && translationStatuses[locale])
    : [];

  return (
    <section className="space-y-5 rounded-xl border border-border bg-surface p-4 shadow-sm sm:p-6" aria-label={t('previewTour')}>
      <h2 lang={sourceLocale} className="text-2xl font-bold text-bookly-navy">{title || '—'}</h2>
      {images.length > 0 && <ImageGallery images={images} title={title} />}
      {description && (
        /<[a-z][\s\S]*>/i.test(description) ? (
          <div
            lang={sourceLocale}
            className="prose prose-sm max-w-none text-text-muted"
            dangerouslySetInnerHTML={{ __html: sanitizeHtml(description) }}
          />
        ) : (
          <p lang={sourceLocale} className="whitespace-pre-line text-text-muted">{description}</p>
        )
      )}
      {highlights.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('highlights')}</h3>
          <ul lang={sourceLocale} className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {highlights.map((item, index) => <li key={`highlight-${index}`}>{item}</li>)}
          </ul>
        </div>
      )}
      {itinerary.length > 0 && (
        <div className="space-y-4">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('itinerary')}</h3>
          {itinerary.map((day, index) => (
            <div key={`day-${index}-${day.day}`} className="border-l-2 border-bookly-gold pl-4">
              <h4 className="font-semibold text-bookly-navy">{t('dayNumber', { number: day.day })}: <span lang={sourceLocale}>{day.title}</span></h4>
              {day.description && <p lang={sourceLocale} className="mt-1 text-sm text-text-muted">{day.description}</p>}
              {(day.stops ?? []).length > 0 && (
                <ol lang={sourceLocale} className="mt-2 list-inside list-decimal space-y-1 text-sm text-bookly-navy">
                  {day.stops?.map((stop, stopIndex) => <li key={`day-${index}-stop-${stopIndex}`}>{stop.title}</li>)}
                </ol>
              )}
            </div>
          ))}
        </div>
      )}
      {inclusions.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('inclusions')}</h3>
          <ul lang={sourceLocale} className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {inclusions.map((item, index) => <li key={`inclusion-${index}`}>{item}</li>)}
          </ul>
        </div>
      )}
      {exclusions.length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('exclusions')}</h3>
          <ul lang={sourceLocale} className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {exclusions.map((item, index) => <li key={`exclusion-${index}`}>{item}</li>)}
          </ul>
        </div>
      )}
      {(important_information ?? []).length > 0 && (
        <div className="space-y-2">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('importantInformation')}</h3>
          <ul lang={sourceLocale} className="list-inside list-disc space-y-1 text-sm text-bookly-navy">
            {(important_information ?? []).map((item, index) => <li key={`important-${index}`}>{item}</li>)}
          </ul>
        </div>
      )}
      {meeting_point && (
        <p className="text-sm text-text-muted">{t('meetingPoint')}: <span lang={sourceLocale}>{meeting_point}</span></p>
      )}
      {cancellation_policy && (
        <p className="text-sm text-text-muted">{t('cancellationPolicy')}: <span lang={sourceLocale}>{cancellation_policy}</span></p>
      )}
      {readinessRows.length > 0 && (
        <div className="space-y-2 border-t border-border pt-4">
          <h3 className="text-xl font-semibold text-bookly-navy">{t('savedTranslations')}</h3>
          <ul className="space-y-1 text-sm text-text-muted">
            {readinessRows.map((locale) => (
              <li key={`saved-${locale}`}>
                {locale.toUpperCase()}: {t(`translationStatus.${translationStatuses![locale]}`)}
              </li>
            ))}
          </ul>
          <p className="text-xs text-text-muted">{t('previewReadinessNote')}</p>
        </div>
      )}
    </section>
  );
}
