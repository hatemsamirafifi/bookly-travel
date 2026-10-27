import { useTranslations } from 'next-intl';
import { getGuideLanguageNames } from '@/lib/tours/detail-presentation';

interface TourFactsProps {
  locale: string;
  durationValue: string;
  groupMin: number;
  groupMax: number;
  difficulty?: 'easy' | 'moderate' | 'challenging' | null;
  guideLanguages: string[];
}

/** Facts: supplied duration, party bounds, optional difficulty and localized
 *  live-guide names. Absent difficulty/guide languages are omitted. */
export default function TourFacts({
  locale,
  durationValue,
  groupMin,
  groupMax,
  difficulty,
  guideLanguages,
}: TourFactsProps) {
  const t = useTranslations('tour');
  const guideNames = getGuideLanguageNames(guideLanguages, locale);

  return (
    <section id="facts" className="scroll-mt-20 rounded-xl border border-border bg-surface p-5">
      <h2 className="mb-3 text-section-title font-bold text-primary">{t('facts')}</h2>
      <dl className="grid gap-4 text-sm sm:grid-cols-2">
        <div><dt className="font-medium text-text-muted">{t('duration')}</dt><dd className="mt-1 text-primary">{durationValue}</dd></div>
        <div><dt className="font-medium text-text-muted">{t('groupSize')}</dt><dd className="mt-1 text-primary">{t('groupRange', { min: groupMin, max: groupMax })}</dd></div>
        {difficulty && <div><dt className="font-medium text-text-muted">{t('difficulty')}</dt><dd className="mt-1 text-primary">{t(`difficulty_${difficulty}`)}</dd></div>}
      </dl>
      {guideNames.length > 0 && (
        <p className="mt-4 text-sm text-primary">
          <span className="font-semibold">{t('liveGuide')}:</span>{' '}
          {guideNames.join(', ')}
        </p>
      )}
    </section>
  );
}
