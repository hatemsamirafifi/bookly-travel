import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';

// Tour-specific 404 for unknown/unpublished slugs under /tours/[slug]. The
// public layout already provides <main>, so this renders no nested landmark.
// The locale and copy come from the real next-intl request context and its
// EN/ES/IT catalogs; no fallback strings are hardcoded here. Withdrawn
// (archived) tours do not land here: the page renders their distinct
// withdrawn state directly instead of calling notFound().
export default async function TourNotFound() {
  const locale = await getLocale();
  const t = await getTranslations({ locale, namespace: 'tour.states' });
  const nav = await getTranslations({ locale, namespace: 'notFound' });

  return (
    <div className="flex flex-col items-center px-4 py-12 text-center">
      <div className="w-full max-w-xl rounded-2xl border border-border bg-surface px-6 py-10 shadow-sm sm:px-10">
        <h1 className="text-xl font-bold text-primary">{t('notFoundTitle')}</h1>
        <p className="mt-2 text-text-muted">{t('notFoundDescription')}</p>
        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
          <Link
            href={`/${locale}`}
            className="rounded-xl bg-bookly-gold px-5 py-2.5 text-sm font-semibold text-bookly-navy transition-colors hover:bg-accent-dark"
          >
            {nav('goHome')}
          </Link>
          <Link
            href={`/${locale}/search`}
            className="rounded-xl border border-border bg-surface-alt px-5 py-2.5 text-sm font-semibold text-bookly-navy transition-colors hover:bg-border"
          >
            {nav('browseTours')}
          </Link>
        </div>
      </div>
    </div>
  );
}
