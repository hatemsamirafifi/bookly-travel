'use client';

import { useTranslations } from 'next-intl';

export default function SearchLoading() {
  const t = useTranslations('search');

  return (
    <div className="mx-auto max-w-7xl px-4 py-8" role="status" aria-live="polite">
      <p className="mb-6 text-sm text-text-muted">{t('loading')}</p>
      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" aria-hidden="true">
        {[0, 1, 2].map((item) => (
          <div key={item} className="h-72 animate-pulse rounded-xl border border-border bg-surface motion-reduce:animate-none" />
        ))}
      </div>
    </div>
  );
}
