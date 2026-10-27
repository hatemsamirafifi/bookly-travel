import { useTranslations } from 'next-intl';
import { Accordion } from '@/components/ui/Accordion';
import { usableEntries } from '@/lib/tours/detail-presentation';

interface TourInclusionsProps {
  inclusions: string[];
  exclusions: string[];
  contentLocale: string;
}

/** Inclusions/exclusions through the shared Accordion, one anchor per list. */
export default function TourInclusions({ inclusions, exclusions, contentLocale }: TourInclusionsProps) {
  const t = useTranslations('tour');
  const includedEntries = usableEntries(inclusions);
  const excludedEntries = usableEntries(exclusions);
  if (includedEntries.length === 0 && excludedEntries.length === 0) return null;

  return (
    <div className="grid gap-6">
      {includedEntries.length > 0 && (
        <section id="included" className="scroll-mt-20">
          <Accordion
            items={[
              {
                id: 'included',
                title: t('included'),
                content: (
                  <ul lang={contentLocale} className="space-y-1">
                    {includedEntries.map((item, index) => (
                      <li key={`inclusion-${index}`} className="flex items-start gap-2 text-sm text-primary/80">
                        <svg className="mt-0.5 h-4 w-4 shrink-0 text-trust" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        {item}
                      </li>
                    ))}
                  </ul>
                ),
              },
            ]}
          />
        </section>
      )}
      {excludedEntries.length > 0 && (
        <section id="excluded" className="scroll-mt-20">
          <Accordion
            items={[
              {
                id: 'excluded',
                title: t('excluded'),
                content: (
                  <ul lang={contentLocale} className="space-y-1">
                    {excludedEntries.map((item, index) => (
                      <li key={`exclusion-${index}`} className="flex items-start gap-2 text-sm text-primary/80">
                        <svg className="mt-0.5 h-4 w-4 shrink-0 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {item}
                      </li>
                    ))}
                  </ul>
                ),
              },
            ]}
          />
        </section>
      )}
    </div>
  );
}
