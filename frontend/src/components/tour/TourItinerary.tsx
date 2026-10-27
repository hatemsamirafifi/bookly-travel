import { useTranslations } from 'next-intl';
import type { TourItineraryDay } from '@/lib/api/types';
import { hasUsableItinerary } from '@/lib/tours/detail-presentation';

interface TourItineraryProps {
  days: TourItineraryDay[];
  itineraryLocale: string;
}

/** Itinerary: authored day/stop order, text and actual language preserved. */
export default function TourItinerary({ days, itineraryLocale }: TourItineraryProps) {
  const t = useTranslations('tour');
  if (!hasUsableItinerary(days)) return null;

  return (
    <section id="itinerary" className="scroll-mt-20">
      <h2 className="mb-3 text-section-title font-bold text-primary">{t('itinerary')}</h2>
      <ol className="space-y-5">
        {days.map((day, dayIndex) => (
          <li key={`${day.day}-${dayIndex}`} className="rounded-lg border border-border bg-surface p-4">
            <h3 className="font-semibold text-primary">{t('day', { number: day.day })}: <span lang={itineraryLocale}>{day.title}</span></h3>
            {day.description && <p lang={itineraryLocale} className="mt-2 text-text-muted">{day.description}</p>}
            {day.stops && day.stops.length > 0 && (
              <ul lang={itineraryLocale} className="mt-3 list-disc space-y-1 pl-5 text-text-muted">
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
  );
}
