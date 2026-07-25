'use client';

import { useTranslations } from 'next-intl';

/**
 * Localized fallback UI rendered by {@link ErrorBoundary} when a child throws.
 *
 * Kept as a separate client component because `ErrorBoundary` is a React class
 * component and cannot call the `useTranslations` hook. The class boundary
 * still owns error capture (Sentry reporting); this component only owns the
 * rendered fallback copy. It must stay hook-free of anything but next-intl and
 * must not itself throw on missing messages (next-intl falls back to the key).
 */
export default function ErrorFallback() {
  const t = useTranslations('errorBoundary');

  return (
    <div className="flex min-h-[50vh] flex-col items-center justify-center px-4 text-center">
      <h2 className="mb-2 text-xl font-semibold text-[#0A2540]">{t('title')}</h2>
      <p className="mb-6 text-sm text-[#5A6B7B]">{t('description')}</p>
      <button
        type="button"
        onClick={() => window.location.reload()}
        className="rounded-xl bg-[#0A2540] px-4 py-2 text-sm font-medium text-white hover:bg-[#071b2e] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0A2540]/40"
      >
        {t('reload')}
      </button>
    </div>
  );
}