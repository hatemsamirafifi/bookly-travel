import { useTranslations } from 'next-intl';
import { usableEntries } from '@/lib/tours/detail-presentation';

interface TourHighlightsProps {
  items: string[];
  contentLocale: string;
}

/** Highlights: supplied entries verbatim in their actual language. */
export default function TourHighlights({ items, contentLocale }: TourHighlightsProps) {
  const t = useTranslations('tour');
  const entries = usableEntries(items);
  if (entries.length === 0) return null;

  return (
    <section id="highlights" className="scroll-mt-20">
      <h2 className="mb-3 text-section-title font-bold text-primary">{t('highlights')}</h2>
      <ul lang={contentLocale} className="space-y-2">
        {entries.map((highlight, index) => (
          <li key={`highlight-${index}`} className="flex items-start gap-2 text-primary/80">
            <svg className="mt-0.5 h-5 w-5 shrink-0 text-trust" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
            {highlight}
          </li>
        ))}
      </ul>
    </section>
  );
}
