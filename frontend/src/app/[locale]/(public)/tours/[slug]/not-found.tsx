import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { routing } from '@/i18n/routing';

// Tour-specific 404 for unknown/unpublished slugs under /tours/[slug]. The
// public layout already provides <main id="main-content">, so this renders no
// nested landmark. The locale is resolved via `getLocale()` (never from
// `params`, which is undefined when a `notFound()` boundary renders) with a
// default-locale fallback, so this component can never crash on a missing or
// unsupported locale. The h1 carries both the generic status code and the
// localized tour title; the remaining copy and recovery links come from the
// shared `notFound` catalog. Withdrawn (archived) tours do not land here: the
// page renders their distinct withdrawn state directly instead of calling
// notFound().
export default async function TourNotFound() {
  let locale: string = routing.defaultLocale;
  try {
    const negotiated = await getLocale();
    if ((routing.locales as readonly string[]).includes(negotiated)) {
      locale = negotiated;
    }
  } catch {
    // Keep the default locale — a 404 page must render, not throw.
  }

  const t = await getTranslations({ locale, namespace: 'tour.states' });
  const nav = await getTranslations({ locale, namespace: 'notFound' });

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 py-12 text-center">
      <div className="w-full max-w-xl rounded-2xl border border-border bg-surface px-6 py-10 shadow-sm sm:px-10">
        <h1 className="text-4xl font-extrabold text-bookly-navy sm:text-5xl">
          {`404 — ${t('notFoundTitle')}`}
        </h1>
        <p className="mt-4 text-xl font-semibold text-bookly-navy">{nav('title')}</p>
        <p className="mt-2 text-text-muted">{t('notFoundDescription')}</p>
        <p className="mt-2 text-text-muted">{nav('subtitle')}</p>
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
