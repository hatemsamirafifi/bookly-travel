'use client';

import { useParams, usePathname, useSearchParams } from 'next/navigation';
import { locales } from '@/i18n/routing';
import { useTranslations } from 'next-intl';

const LOCALE_LABELS: Record<string, string> = {
  en: 'English',
  es: 'Español',
  it: 'Italiano',
};

export default function LocaleSwitcher() {
  const t = useTranslations('nav');
  const params = useParams();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const currentLocale = (params?.locale as string) || 'en';

  const switchTo = (locale: string) => {
    if (locale === currentLocale) return;
    const newPath = pathname.replace(new RegExp(`^/${currentLocale}(?=/|$)`), `/${locale}`);
    const qs = searchParams?.toString();
    const targetUrl = qs ? `${newPath}?${qs}` : newPath;
    window.location.href = targetUrl;
  };

  return (
    <div className="relative">
      <select
        value={currentLocale}
        onChange={(e) => switchTo(e.target.value)}
        className="min-h-11 rounded-md border border-border bg-surface px-2 py-1 text-sm text-primary focus-visible:ring-2 focus-visible:ring-focus"
        aria-label={t('switchLanguage')}
      >
        {locales.map((locale) => (
          <option key={locale} value={locale}>
            {LOCALE_LABELS[locale]}
          </option>
        ))}
      </select>
    </div>
  );
}
