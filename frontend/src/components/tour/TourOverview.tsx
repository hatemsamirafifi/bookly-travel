'use client';

import { useId, useRef, useState } from 'react';
import { useTranslations } from 'next-intl';

/** Preview budget for long descriptions, counted in Unicode code points. */
export const DESCRIPTION_PREVIEW_CODE_POINTS = 600;

interface TourOverviewProps {
  /** Complete selected-language description; rendered verbatim. */
  text: string;
  /** Actual language of the text, independent of the page locale. */
  contentLocale: string;
}

/** Overview island: full text up to 600 Unicode code points, otherwise a
 *  code-point preview with expand/collapse. Collapsing restores focus to the
 *  toggle before hiding a focused panel. */
export default function TourOverview({ text, contentLocale }: TourOverviewProps) {
  const t = useTranslations('tour');
  const overviewT = useTranslations('tour.overview');
  const [expanded, setExpanded] = useState(false);
  const panelId = useId();
  const toggleRef = useRef<HTMLButtonElement>(null);
  const panelRef = useRef<HTMLDivElement>(null);

  const codePoints = Array.from(text);
  if (codePoints.length <= DESCRIPTION_PREVIEW_CODE_POINTS) {
    return (
      <section id="overview" className="scroll-mt-20">
        <h2 className="mb-3 text-section-title font-bold text-primary">{t('about')}</h2>
        <div lang={contentLocale} className="prose prose-gray max-w-none text-primary/80 whitespace-pre-line">
          {text}
        </div>
      </section>
    );
  }

  const preview = codePoints.slice(0, DESCRIPTION_PREVIEW_CODE_POINTS).join('');

  const handleToggle = () => {
    if (expanded && panelRef.current?.contains(document.activeElement)) {
      toggleRef.current?.focus();
    }
    setExpanded((current) => !current);
  };

  return (
    <section id="overview" className="scroll-mt-20">
      <h2 className="mb-3 text-section-title font-bold text-primary">{t('about')}</h2>
      <div
        id={panelId}
        ref={panelRef}
        lang={contentLocale}
        className="prose prose-gray max-w-none text-primary/80 whitespace-pre-line"
      >
        {expanded ? text : preview}
      </div>
      <button
        ref={toggleRef}
        type="button"
        aria-expanded={expanded}
        aria-controls={panelId}
        onClick={handleToggle}
        className="mt-3 inline-flex min-h-11 items-center font-semibold text-primary underline hover:no-underline focus-visible:outline-2 focus-visible:outline-focus"
      >
        {expanded ? overviewT('readLess') : overviewT('readMore')}
      </button>
    </section>
  );
}
