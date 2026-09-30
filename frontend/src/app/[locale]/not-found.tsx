import Link from 'next/link';
import { getLocale, getTranslations } from 'next-intl/server';
import { routing } from '@/i18n/routing';

// Localized 404 for every unmatched route under `/[locale]/*`. The locale is
// resolved via `getLocale()` (never from `params`, which is undefined when a
// `notFound()` boundary renders) with a default-locale fallback, so this
// component can never crash on a missing or unsupported locale.
export default async function NotFound() {
  let locale: string = routing.defaultLocale;
  try {
    const negotiated = await getLocale();
    if ((routing.locales as readonly string[]).includes(negotiated)) {
      locale = negotiated;
    }
  } catch {
    // Keep the default locale — a 404 page must render, not throw.
  }

  const t = await getTranslations({ locale, namespace: 'notFound' });

  return (
    <main className="flex min-h-[60vh] flex-col items-center justify-center bg-surface-alt px-4 py-12 text-center">
      <div className="w-full max-w-xl rounded-2xl border border-border bg-surface px-6 py-10 shadow-sm sm:px-10">
      <h1 className="text-6xl font-extrabold text-bookly-navy">404</h1>
      <p className="mt-4 text-xl font-semibold text-bookly-navy">{t('title')}</p>
      <p className="mt-2 text-text-muted">{t('subtitle')}</p>
      <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
        <Link
          href={`/${locale}`}
          className="rounded-xl bg-bookly-gold px-5 py-2.5 text-sm font-semibold text-bookly-navy transition-colors hover:bg-accent-dark"
        >
          {t('goHome')}
        </Link>
        <Link
          href={`/${locale}/search`}
          className="rounded-xl border border-border bg-surface-alt px-5 py-2.5 text-sm font-semibold text-bookly-navy transition-colors hover:bg-border"
        >
          {t('browseTours')}
        </Link>
      </div>
      </div>
    </main>
  );
}
