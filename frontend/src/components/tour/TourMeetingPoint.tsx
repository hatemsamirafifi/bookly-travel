import { useTranslations } from 'next-intl';
import { isUsableText, usableEntries } from '@/lib/tours/detail-presentation';

/** Practical sections: fully readable in their actual language, no invention. */
export function TourMeetingPoint({ meetingPoint, contentLocale }: { meetingPoint: string; contentLocale: string }) {
  const t = useTranslations('tour');
  if (!isUsableText(meetingPoint)) return null;

  return (
    <section id="meeting" className="scroll-mt-20">
      <h2 className="mb-2 text-section-title font-bold text-primary">{t('meetingPoint')}</h2>
      <p lang={contentLocale} className="flex items-start gap-2 text-primary/80 whitespace-pre-line">
        <svg className="mt-0.5 h-5 w-5 shrink-0 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
        {meetingPoint}
      </p>
    </section>
  );
}

export function TourCancellation({ cancellationPolicy, contentLocale }: { cancellationPolicy: string; contentLocale: string }) {
  const t = useTranslations('tour');
  if (!isUsableText(cancellationPolicy)) return null;

  return (
    <section id="cancellation" className="scroll-mt-20">
      <h2 className="mb-2 text-section-title font-bold text-primary">{t('cancellationPolicy')}</h2>
      <p lang={contentLocale} className="text-primary/80 whitespace-pre-line">{cancellationPolicy}</p>
    </section>
  );
}

export function TourImportantInformation({ items, contentLocale }: { items: string[]; contentLocale: string }) {
  const t = useTranslations('tour');
  const entries = usableEntries(items);
  if (entries.length === 0) return null;

  return (
    <section id="important" className="scroll-mt-20">
      <h2 className="mb-2 text-section-title font-bold text-primary">{t('importantInformation')}</h2>
      <ul lang={contentLocale} className="list-disc space-y-1 pl-5 text-primary/80">
        {entries.map((item, index) => <li key={`important-${index}`}>{item}</li>)}
      </ul>
    </section>
  );
}
