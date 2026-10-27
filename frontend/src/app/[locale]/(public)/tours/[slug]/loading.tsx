import { getLocale, getTranslations } from 'next-intl/server';
import LoadingSkeleton from '@/components/ui/LoadingSkeleton';

// Route-local loading geometry for the tour detail page. The public layout
// already provides <main>, so this renders a status region plus the shared
// detail skeleton shape without nesting another landmark. The locale and copy
// come from the real next-intl request context and its EN/ES/IT catalogs;
// no fallback strings are hardcoded here.
export default async function TourDetailLoading() {
  const locale = await getLocale();
  const t = await getTranslations({ locale, namespace: 'tour.states' });

  return (
    // Full-viewport minimum so the footer stays below the fold until the taller streamed detail replaces this skeleton.
    <div className="min-h-screen py-8" role="status" aria-live="polite">
      <p className="mb-6 text-sm text-text-muted">{t('loadingMessage')}</p>
      <div aria-hidden="true">
        <LoadingSkeleton variant="detail" />
      </div>
    </div>
  );
}
